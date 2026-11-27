<?php

namespace Tests\Unit;

use App\Services\PinnacleEdge;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class PinnacleEdgeTest extends TestCase
{
    #[TestWith([4, 3.67, '+9.0%'])]
    #[TestWith([7.2, 6.5, '+10.8%'])]
    #[TestWith([7.2, 7.21, '-0.1%'])]
    #[TestWith([3.48, 3.67, '-5.2%'])]
    #[TestWith([3.67, 3.67, '0.0%'])]
    #[TestWith([3.66999, 3.67, '0.0%'])]
    #[TestWith([null, 3.67, 'N/A'])]
    #[TestWith([4, null, 'N/A'])]
    #[TestWith([0, 3.67, 'N/A'])]
    #[TestWith([4, 1, 'N/A'])]
    public function test_edge_compares_betfair_with_fair_nvp_without_using_the_raw_pinnacle_price(mixed $betfair, mixed $nvp, string $expected): void
    {
        $this->assertSame($expected, PinnacleEdge::format(PinnacleEdge::calculate($betfair, $nvp)));
    }

    public function test_only_the_matching_outcomes_valid_nvp_can_be_used(): void
    {
        $drop = ['to_price' => 3.48, 'outcome' => 'Away', 'full_market' => [
            ['outcome' => 'Home', 'nvp' => 2.2], ['outcome' => 'Away', 'nvp' => 3.67],
        ]];
        $this->assertSame(3.67, PinnacleEdge::fairPrice($drop));
        $this->assertNull(PinnacleEdge::fairPrice(['to_price' => 3.48]));
        $this->assertNull(PinnacleEdge::calculate(INF, 2));
        $this->assertNull(PinnacleEdge::calculate(2, NAN));
        $this->assertNull(PinnacleEdge::calculate(PHP_FLOAT_MAX, 1.01));
    }
}
