<?php

namespace Tests\Unit;

use App\Services\BetfairOrderBook;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BetfairOrderBookTest extends TestCase
{
    public static function books(): array
    {
        $runner = ['marketId' => '1.12345678', 'runnerId' => 11, 'runner' => 'საქართველო', 'marketType' => 'MATCH_ODDS',
            'backOdds' => 1.23456789, 'layOdds' => 1.25, 'backSize' => 0, 'laySize' => 154.987654321,
            'handicap' => -0.5, 'suspended' => false, 'optional' => null,
            'depth' => [['price' => 1.23, 'size' => 0.0123456789]]];

        return [
            'homogeneous rows' => [['id' => 123, 'markets' => [$runner, array_replace($runner, ['runnerId' => 12, 'backOdds' => 2.0])], 'status' => 'OPEN']],
            'keyed rows' => [['markets' => ['11|0' => $runner, '12|0' => $runner]]],
            'sparse numeric rows' => [['markets' => [2 => $runner, 7 => $runner]]],
            'different optional fields' => [['markets' => [$runner, $runner + ['extra' => ['any' => 'value']]]]],
            'different field order' => [['markets' => [$runner, array_reverse($runner, true)]]],
            'empty rows' => [['markets' => [[], []]]],
            'empty book' => [['markets' => []]],
            'missing book' => [['id' => 123]],
            'null book' => [['markets' => null]],
            'unexpected row' => [['markets' => [$runner, null, 'unchanged']]],
        ];
    }

    #[DataProvider('books')]
    public function test_compact_storage_preserves_exact_fields_types_values_keys_and_order(array $event): void
    {
        $book = BetfairOrderBook::fromArray($event);

        $this->assertSame($event, $book->toArray());
        $this->assertSame(json_encode($event), json_encode($book->toArray()));
    }

    public function test_partial_updates_never_mutate_the_stored_snapshot_or_lose_other_runners(): void
    {
        $event = ['markets' => [['runnerId' => 1, 'backOdds' => 1.8], ['runnerId' => 2, 'backOdds' => 2.0]]];
        $book = BetfairOrderBook::fromArray($event);
        $next = $book->toArray();
        $next['markets'][0]['backOdds'] = 1.9;
        $updated = BetfairOrderBook::fromArray($next);

        $this->assertSame($event, $book->toArray());
        $this->assertSame(1.9, $updated->toArray()['markets'][0]['backOdds']);
        $this->assertSame($event['markets'][1], $updated->toArray()['markets'][1]);
    }
}
