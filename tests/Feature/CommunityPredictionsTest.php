<?php

namespace Tests\Feature;

use App\Models\PredictionVote;
use App\Repositories\CustomerRepository;
use App\Services\CustomerAuthService;
use Database\Factories\CustomerFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class CommunityPredictionsTest extends TestCase
{
    use RefreshDatabase;

    private array $match;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['services.football_fixture_index' => false]);
        $auth = \Mockery::mock(CustomerAuthService::class, [app(CustomerRepository::class)])->makePartial();
        $auth->shouldReceive('handle')->andReturnUsing(fn ($request, $next) => $next($request));
        $this->app->instance(CustomerAuthService::class, $auth);
        $this->match = ['unique_key' => 'community-match', 'sportsApiPro_match_id' => '12345', 'team1' => 'Denmark', 'team2' => 'Norway',
            'tournament' => 'UEFA Nations League', 'result_status' => 'Prematch', 'timestamp' => time() + 3600, 'data_sync_complete' => true];
        Redis::shouldReceive('get')->andReturnUsing(fn (string $key): ?string => $key === 'Football:matches' ? json_encode(['community-match' => $this->match]) : null);
    }

    public function test_every_viewer_sees_global_counts_before_voting_and_only_their_own_selection(): void
    {
        $first = CustomerFactory::new()->create();
        $second = CustomerFactory::new()->create();
        foreach ([[$first, '1x2', '1'], [$second, '1x2', 'X'], [$first, 'uo', 'UNDER']] as [$customer, $poll, $value]) {
            PredictionVote::query()->create(['vote_key' => hash('sha256', $customer->id.'|'.$poll), 'customer_id' => $customer->id,
                'sport' => 'football', 'match_id' => '12345', 'poll' => $poll, 'value' => $value]);
        }

        $this->getJson('/football/matches/12345/predictions')->assertOk()
            ->assertJsonPath('locked', false)->assertJsonPath('polls.1x2.counts.1', 1)
            ->assertJsonPath('polls.1x2.counts.X', 1)->assertJsonPath('polls.1x2.counts.2', 0)
            ->assertJsonPath('polls.uo.counts.UNDER', 1)->assertJsonPath('polls.uo.counts.OVER', 0)
            ->assertJsonPath('polls.1x2.value', null)->assertJsonMissingPath('customer_id');
        $this->actingAs($first, 'customers')->getJson('/football/matches/12345/predictions')
            ->assertJsonPath('polls.1x2.value', '1')->assertJsonPath('polls.uo.value', 'UNDER');
        $this->actingAs(CustomerFactory::new()->create(), 'customers')->getJson('/football/matches/12345/predictions')
            ->assertJsonPath('polls.1x2.counts.X', 1)->assertJsonPath('polls.1x2.value', null);
    }

    public function test_votes_are_persistent_once_per_customer_and_market_across_sessions(): void
    {
        $customer = CustomerFactory::new()->create();
        $this->actingAs($customer, 'customers');
        $this->postJson('/football/filter-football', ['prediction' => ['match_id' => '12345', 'poll' => '1x2', 'value' => '1']])
            ->assertOk()->assertJsonPath('prediction.value', '1')->assertJsonPath('prediction.counts.1', 1);
        $this->app['session']->flush();
        $this->postJson('/football/filter-football', ['prediction' => ['match_id' => '12345', 'poll' => '1x2', 'value' => '2']])
            ->assertOk()->assertJsonPath('prediction.value', '1')->assertJsonPath('prediction.counts.1', 1);
        $this->postJson('/football/filter-football', ['prediction' => ['match_id' => '12345', 'poll' => 'uo', 'value' => 'OVER']])
            ->assertOk()->assertJsonPath('prediction.counts.OVER', 1);
        $this->actingAs(CustomerFactory::new()->create(), 'customers')->postJson('/football/filter-football', ['prediction' => ['match_id' => '12345', 'poll' => '1x2', 'value' => '2']])
            ->assertOk()->assertJsonPath('prediction.counts.1', 1)->assertJsonPath('prediction.counts.2', 1);

        $this->assertDatabaseCount('prediction_votes', 3);
        $this->assertDatabaseHas('prediction_votes', ['customer_id' => $customer->id, 'poll' => '1x2', 'value' => '1']);
        $this->assertDatabaseMissing('prediction_votes', ['customer_id' => $customer->id, 'poll' => '1x2', 'value' => '2']);
    }

    public function test_fixture_modal_renders_percentages_before_the_viewer_votes(): void
    {
        $this->withoutVite();
        $this->match['tournament_country'] = 'Europe';
        $this->match['betfair_match_id'] = '900';
        $this->match['minutes_status'] = 'Prematch';
        $this->match['markets'] = ['main' => [
            ['marketType' => 'MATCH_ODDS', 'runner' => 'Denmark', 'currentBackOdds' => 2.1],
            ['marketType' => 'MATCH_ODDS', 'runner' => 'Norway', 'currentBackOdds' => 2.8],
        ]];
        foreach (['1', '1', 'X'] as $index => $value) {
            PredictionVote::query()->create(['vote_key' => hash('sha256', 'viewer-'.$index), 'customer_id' => CustomerFactory::new()->create()->id,
                'sport' => 'football', 'match_id' => '12345', 'poll' => '1x2', 'value' => $value]);
        }

        $this->get('/')->assertOk()->assertSee('data-prediction-results class="flex', false)
            ->assertSee('67%', false)->assertSee('33%', false)->assertSee('0%', false)
            ->assertSee('/football/matches/12345/predictions', false);
    }

    public function test_guests_and_invalid_selections_cannot_vote(): void
    {
        $prediction = ['match_id' => '12345', 'poll' => '1x2', 'value' => '1'];
        $this->postJson('/football/filter-football', ['prediction' => $prediction])->assertUnauthorized();
        $this->actingAs(CustomerFactory::new()->create(), 'customers')->postJson('/football/filter-football', ['prediction' => array_replace($prediction, ['value' => 'OVER'])])->assertUnprocessable();
        $this->assertDatabaseCount('prediction_votes', 0);
    }

    public function test_poll_totals_remain_readable_but_new_votes_close_at_kickoff_even_with_stale_prematch_status(): void
    {
        $this->match['timestamp'] = time() - 1;
        $this->getJson('/football/matches/12345/predictions')->assertOk()->assertJsonPath('locked', true);
        $this->actingAs(CustomerFactory::new()->create(), 'customers')->postJson('/football/filter-football', ['prediction' => ['match_id' => '12345', 'poll' => 'uo', 'value' => 'UNDER']])->assertUnprocessable();
        $this->assertDatabaseCount('prediction_votes', 0);
    }

    public function test_missing_matches_return_not_found_and_personal_results_are_never_cached(): void
    {
        $this->getJson('/football/matches/999/predictions')->assertNotFound();
        $this->getJson('/football/matches/invalid/predictions')->assertNotFound();
        $response = $this->getJson('/football/matches/12345/predictions');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
    }
}
