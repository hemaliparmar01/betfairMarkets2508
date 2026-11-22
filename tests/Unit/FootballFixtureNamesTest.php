<?php

namespace Tests\Unit;

use App\Services\FootballFixtureNames;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class FootballFixtureNamesTest extends TestCase
{
    #[TestWith(['Man Utd', 'Manchester United'])]
    #[TestWith(['Liverpool F.C.', 'Liverpool'])]
    #[TestWith(['Mönchengladbach', 'Mgladbach'])]
    #[TestWith(['Malmö FF', 'Malmo'])]
    #[TestWith(['Unione Venezia', 'Venezia'])]
    #[TestWith(['Paris Saint-Germain', 'PSG'])]
    #[TestWith(['FC Copenhagen', 'Copenhagen'])]
    #[TestWith(['Celta Vigo II', 'Celta Vigo B'])]
    #[TestWith(['Man Utd U-21', 'Manchester United Under 21'])]
    #[TestWith(['Man Utd (W)', 'Manchester United Women'])]
    public function test_known_equivalent_clubs_have_the_same_identity(string $left, string $right): void
    {
        $this->assertSame(100.0, FootballFixtureNames::similarity($left, $right));
    }

    #[TestWith(['Man Utd', 'Manchester City'])]
    #[TestWith(['Man Utd', 'Manchester United Women'])]
    #[TestWith(['Liverpool U21', 'Liverpool'])]
    #[TestWith(['Celta Vigo B', 'Celta Vigo'])]
    #[TestWith(['Celta Vigo B', 'Celta Vigo C'])]
    #[TestWith(['Malmo FF', 'IFK Malmo'])]
    #[TestWith(['Arsenal', 'Arsenal Sarandi'])]
    #[TestWith(['', ''])]
    public function test_different_clubs_or_squads_do_not_match(string $left, string $right): void
    {
        $this->assertLessThan(80.0, FootballFixtureNames::similarity($left, $right));
    }

    #[TestWith(['Sassuolo Calcio a VS I Genoa', 'Sassuolo', 100])]
    #[TestWith(['Sassuolo Calcio a VS I Genoa', 'Genoa', 100])]
    #[TestWith(["Hull City\n0:0\nBrighton &\nHove Albion", 'Hull', 100])]
    #[TestWith(["Hull City\n0:0\nBrighton &\nHove Albion", 'Brighton', 100])]
    #[TestWith(["Paris\n0:0\nAngers SCO", 'Paris Saint Germain', 50])]
    #[TestWith(["Paris\n0:0\nAngers SCO", 'Angers', 100])]
    #[TestWith(["Paris\n0:0\nAngers SCO", 'Paris Saint Germain Women', 0])]
    #[TestWith(['Monaco vs Auxerre', 'Auxerre United', 0])]
    #[TestWith(['Monaco vs Auxerre', 'Liverpool', 0])]
    public function test_card_mentions_preserve_readable_teams_without_guessing_unrelated_names(string $text, string $team, int $score): void
    {
        $this->assertSame($score, FootballFixtureNames::mentionScore($text, $team));
    }
}
