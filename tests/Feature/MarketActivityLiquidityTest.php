<?php

namespace Tests\Feature;

use App\Services\MarketActivityLiquidity;
use App\Services\MarketActivityStream;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class MarketActivityLiquidityTest extends TestCase
{
    #[TestWith([1.8, 1.82, 100, 100, false])]
    #[TestWith([1.8, 1.82, 25, 250, true])]
    #[TestWith([380, 0, 4.03, 0, false])]
    #[TestWith([0, 1.01, 0, 494.5, false])]
    #[TestWith([1.8, 1.82, 24.99, 100, false])]
    #[TestWith([1.8, 1.82, 25, 125, false])]
    #[TestWith([1.8, 1.82, 125, 25, false])]
    #[TestWith([1.8, 1.7, 100, 600, false])]
    #[TestWith([1.8, 1.82, 25, 125.01, true])]
    #[TestWith([1.8, 1.82, 125.01, 25, true])]
    public function test_only_fresh_two_sided_liquid_books_are_visible(float $back, float $lay, float $backSize, float $laySize, bool $expected): void
    {
        $event = $this->event();
        $event['runners'][0] = compact('back', 'lay', 'backSize', 'laySize');

        $this->assertSame($expected, MarketActivityLiquidity::visible($event, 1000));
    }

    public function test_missing_stale_suspended_and_non_finite_books_are_hidden(): void
    {
        foreach ([['stale' => true], ['freshUntil' => 1000], ['active' => false], ['liquidityValid' => false], ['runners' => []], ['removed' => true]] as $override) {
            $this->assertFalse(MarketActivityLiquidity::visible(array_replace($this->event(), $override), 1000));
        }
        foreach ([null, INF, NAN] as $value) {
            $event = $this->event();
            $event['runners'][0]['backSize'] = $value;
            $this->assertFalse(MarketActivityLiquidity::visible($event, 1000));
        }
    }

    public function test_another_runners_missing_side_cannot_make_the_market_look_liquid(): void
    {
        $event = $this->event();
        $event['runners'][] = ['back' => 3, 'lay' => 0, 'backSize' => 1000, 'laySize' => 0];
        $this->assertFalse(MarketActivityLiquidity::visible($event, 1000));
    }

    public function test_limits_are_configurable_and_apply_to_the_favorite_not_combined_totals(): void
    {
        config(['services.market_activity_liquidity.minimum_size' => 200]);
        $event = $this->event();
        $event['runners'][] = ['back' => 3, 'lay' => 3.1, 'backSize' => 10000, 'laySize' => 10000];
        $this->assertFalse(MarketActivityLiquidity::visible($event, 1000));
        config(['services.market_activity_liquidity.minimum_size' => 100]);
        $this->assertTrue(MarketActivityLiquidity::visible($event, 1000));
    }

    public function test_live_quotes_remove_invalid_markets_and_allow_recovery_across_sports(): void
    {
        foreach (array_keys(MarketActivityStream::SPORTS) as $prefix) {
            $runner = ['runnerId' => 1, 'runner' => 'Favorite', 'marketType' => 'WIN', 'backOdds' => 1.8, 'layOdds' => 1.82,
                'backSize' => 100, 'laySize' => 600, 'liquidity_updated_at' => microtime(true)];
            $match = ['result_status' => 'Prematch', 'timestamp' => time() + 120, 'markets' => ['WIN' => [$runner]]];
            $stream = app(MarketActivityStream::class);
            $this->assertFalse($stream->markets($prefix, 'match', $match)[0]['removed']);
            $match['markets']['WIN'][0]['laySize'] = 0;
            $quote = $stream->markets($prefix, 'match', $match)[0];
            $this->assertTrue($quote['removed']);
            $this->assertSame([], $quote['runners']);
            $match['markets']['WIN'][0]['laySize'] = 600;
            $this->assertFalse($stream->markets($prefix, 'match', $match)[0]['removed']);
        }
    }

    /** @return array<string, mixed> */
    private function event(): array
    {
        return ['stale' => false, 'freshUntil' => 1075, 'runners' => [
            ['back' => 1.8, 'lay' => 1.82, 'backSize' => 100, 'laySize' => 600],
        ]];
    }
}
