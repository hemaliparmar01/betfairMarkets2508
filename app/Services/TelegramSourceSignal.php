<?php

namespace App\Services;

class TelegramSourceSignal
{
    /** @return array<string, mixed>|null */
    public static function parse(string $text): ?array
    {
        foreach (preg_split('/\R/u', $text) as $original) {
            $line = trim(preg_replace('/^(?:Market|Prediction|Selection)\s*:\s*/iu', '', trim($original)));
            $period = preg_match('/\b(?:1st|first)[\h-]*half|\bHT\b/iu', $line) ? 1
                : (preg_match('/\b(?:2nd|second)[\h-]*half/iu', $line) ? 2 : 0);
            $line = preg_replace('/\b(?:1st|first|2nd|second|full)[\h-]*(?:half|time)\b|\bHT\b/iu', '', $line);
            $team = null;
            if (preg_match('/^Total\h+([12])(?:\.\h+|\.(?=\()|\h+)\(?(\d+(?:[.,]\d+)?)\)?\h*(Over|Under)\.?\h*(?:[1-9]\d*(?:[.,]\d+)?)?\h*$/iu', trim($line), $teamTotal)) {
                $team = (int) $teamTotal[1];
                $line = $teamTotal[3].' '.$teamTotal[2];
            }
            $line = trim(preg_replace('/^Totals?\.?\s*/iu', '', $line), " \t.-–:");
            $suffix = ['', ' · 1st Half', ' · 2nd Half'][$period];
            if (preg_match('/^(Over|Under)\h*\(?(\d+(?:[.,]\d+)?)\)?(?:\h*Goals?)?\h*[.\-–:]?(?:\h+[1-9]\d*(?:[.,]\d+)?)?\h*$/iu', $line, $parts)) {
                $selection = strtolower($parts[1]);
                $points = (float) str_replace(',', '.', $parts[2]);
                $label = ucfirst($selection).' '.rtrim(rtrim(number_format($points, 2, '.', ''), '0'), '.').' Goals'.$suffix;
                $market = ($period === 1 ? 'FIRST_HALF_GOALS_' : ($period === 2 ? 'SECOND_HALF_GOALS_' : 'OVER_UNDER_')).str_pad((string) (int) round($points * 10), 2, '0', STR_PAD_LEFT);
                $family = $team === null ? 'total_goals' : 'team_total_goals';
                if ($team !== null) {
                    $label = 'Team '.$team.' · '.$label;
                    $market = 'SOURCE_UNMAPPED';
                }
            } elseif (preg_match('/^(?:Both\h+Teams\h+(?:to|will)\h+Score|BTTS)[.\h:–-]*(Yes|No)(?:\h+[1-9]\d*(?:[.,]\d+)?)?$/iu', $line, $parts)) {
                $selection = strtolower($parts[1]);
                $points = null;
                $label = 'BTTS '.ucfirst($selection).$suffix;
                $market = ($period === 1 ? 'FIRST_HALF_' : ($period === 2 ? 'SECOND_HALF_' : '')).'BOTH_TEAMS_TO_SCORE';
                $family = 'btts';
            } elseif (preg_match('/^(?:Match\h+Odds|1X2)[.\h:–-]*(Home|Away|Draw|1|X|2)(?:\h+[1-9]\d*(?:[.,]\d+)?)?$/iu', $line, $parts)) {
                $selection = match (strtolower($parts[1])) {
                    '1', 'home' => 'home', '2', 'away' => 'away', default => 'draw'
                };
                $points = null;
                $label = 'Match Odds · '.ucfirst($selection).$suffix;
                $market = $period === 1 ? 'HALF_TIME' : ($period === 2 ? 'SECOND_HALF_MATCH_ODDS' : 'MATCH_ODDS');
                $family = 'match_odds';
            } else {
                $named = preg_match('/^(?:Asian Handicap|European Handicap|Handicap|Double Chance|Draw No Bet|Correct Score)[.\h:–-]+.+$/iu', $line) === 1;
                if (! $named && ! (preg_match('/^(?:Market|Prediction)\s*:/iu', trim($original))
                    && preg_match('/^Selection\s*:\s*(.+)$/imu', $text, $explicitSelection))) {
                    continue;
                }
                $family = 'other';
                $points = null;
                $selection = mb_strtolower(preg_replace('/\s+/', ' ', $line.($named ? '' : ' · '.trim($explicitSelection[1]))));
                $label = $line.($named ? '' : ' · '.trim($explicitSelection[1])).$suffix;
                $market = 'SOURCE_UNMAPPED';
            }
            $periodKa = ['', ' · 1-ლი ტაიმი', ' · მე-2 ტაიმი'][$period];
            $labelKa = match ($family) {
                'total_goals' => ($selection === 'over' ? 'მეტი' : 'ნაკლები').' '.$points.' გოლი'.$periodKa,
                'team_total_goals' => 'გუნდი '.$team.' · '.($selection === 'over' ? 'მეტი' : 'ნაკლები').' '.$points.' გოლი'.$periodKa,
                'btts' => 'ორივე გუნდი გაიტანს · '.($selection === 'yes' ? 'დიახ' : 'არა').$periodKa,
                'match_odds' => 'მატჩის შედეგი · '.match ($selection) {
                    'home' => 'მასპინძელი', 'away' => 'სტუმარი', default => 'ფრე'
                }.$periodKa,
                default => $label,
            };

            $identity = [$family, $period, $selection, $points];
            if ($team !== null) {
                $identity[] = $team;
            }

            return ['key' => hash('sha256', json_encode($identity)), 'family' => $family, 'label_ka' => $labelKa,
                'period' => $period, 'selection' => $selection, 'points' => $points, 'label' => $label,
                'market_type' => $market, 'is_ltd' => $family === 'total_goals' && $period === 1 && $selection === 'over' && $points === 0.5] + ($team === null ? [] : ['team' => $team]);
        }

        return null;
    }
}
