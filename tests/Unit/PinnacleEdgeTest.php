<?php

namespace Tests\Unit;

use App\Services\PinnacleEdge;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class PinnacleEdgeTest extends TestCase
{
    #[TestWith([1.64, 1.58, '+3.8%'])]
    #[TestWith([5.20, 4.96, '+4.8%'])]
    #[TestWith([4.60, 4.53, '+1.5%'])]
    #[TestWith([4, 3.48, '+14.9%'])]
    #[TestWith([7.2, 6.5, '+10.8%'])]
    #[TestWith([7.2, 7.21, '-0.1%'])]
    #[TestWith([3.48, 3.67, '-5.2%'])]
    #[TestWith([3.67, 3.67, '0.0%'])]
    #[TestWith([3.66999, 3.67, '-<0.1%'])]
    #[TestWith([3.67001, 3.67, '+<0.1%'])]
    #[TestWith([null, 3.67, 'N/A'])]
    #[TestWith([4, null, 'N/A'])]
    #[TestWith([0, 3.67, 'N/A'])]
    #[TestWith([4, 1, 'N/A'])]
    public function test_edge_compares_betfair_with_the_new_pinnacle_price(mixed $betfair, mixed $pinnacle, string $expected): void
    {
        $this->assertSame($expected, PinnacleEdge::format(PinnacleEdge::calculate($betfair, $pinnacle)));
    }

    public function test_separate_nvp_information_still_uses_the_matching_outcome(): void
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
