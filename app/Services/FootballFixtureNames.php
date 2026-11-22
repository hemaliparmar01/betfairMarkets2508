<?php

namespace App\Services;

use Illuminate\Support\Str;

class FootballFixtureNames
{
    private const ALIASES = [
        'man united' => 'manchester united',
        'manchester united' => 'manchester united',
        'manchester utd' => 'manchester united',
        'man utd' => 'manchester united',
        'man city' => 'manchester city',
        'manchester city' => 'manchester city',
        'spurs' => 'tottenham hotspur',
        'tottenham' => 'tottenham hotspur',
        'newcastle' => 'newcastle united',
        'leeds' => 'leeds united',
        'wolves' => 'wolverhampton wanderers',
        'west ham' => 'west ham united',
        'nottm forest' => 'nottingham forest',
        'notts forest' => 'nottingham forest',
        'sheff united' => 'sheffield united',
        'sheff wednesday' => 'sheffield wednesday',
        'psg' => 'paris saint germain',
        'inter' => 'internazionale milano',
        'inter milan' => 'internazionale milano',
        'internazionale' => 'internazionale milano',
        'as roma' => 'roma',
        'as monaco' => 'monaco',
        'aj auxerre' => 'auxerre',
        'unione venezia' => 'venezia',
        'ol lyonnes' => 'lyon',
        'borussia monchengladbach' => 'borussia monchengladbach',
        'borussia moenchengladbach' => 'borussia monchengladbach',
        'b monchengladbach' => 'borussia monchengladbach',
        'b moenchengladbach' => 'borussia monchengladbach',
        'borussia m gladbach' => 'borussia monchengladbach',
        'm gladbach' => 'borussia monchengladbach',
        'mgladbach' => 'borussia monchengladbach',
        'monchengladbach' => 'borussia monchengladbach',
        'moenchengladbach' => 'borussia monchengladbach',
        'borussia dortmund' => 'borussia dortmund',
        'b dortmund' => 'borussia dortmund',
        'dortmund' => 'borussia dortmund',
        'malmo' => 'malmo ff',
        'malmo ff' => 'malmo ff',
        'hull' => 'hull city',
        'hull city' => 'hull city',
        'brighton' => 'brighton hove albion',
        'brighton and hove albion' => 'brighton hove albion',
        'brighton hove albion' => 'brighton hove albion',
        'angers sco' => 'angers',
        'sco angers' => 'angers',
        'stade rennais' => 'rennes',
    ];

    private static function words(string $name): string
    {
        $name = strtolower(Str::ascii($name));
        $name = str_replace(["women's", 'a.f.c.', 'f.c.', 'c.f.'], ['women', 'afc', 'fc', 'cf'], $name);
        $name = preg_replace_callback('/\b(?:a f c|f c|c f)\b/', static fn (array $parts): string => str_replace(' ', '', $parts[0]), $name);
        $name = preg_replace('/\bunder[ -]?(\d{2})\b|\bu[ -]?(\d{2})\b/', 'u$1$2', $name);
        $name = ' '.trim(preg_replace('/[^a-z0-9]+/', ' ', $name)).' ';

        return trim(str_replace([' utd ', ' ath ', ' st '], [' united ', ' athletic ', ' saint '], $name));
    }

    /** @return array{string, string|null, bool} */
    public static function squad(string $name): array
    {
        $name = self::words($name);
        preg_match('/(?:^| )(iii|ii|b|c)(?: (?:u[0-9]{2}|women|womens|ladies|w))*$/', $name, $reserve);
        preg_match('/(?:^| )u([0-9]{2})(?: |$)/', $name, $age);

        return [match ($reserve[1] ?? '') {
            'ii', 'b' => 'b', 'iii', 'c' => 'c', default => 'first',
        }, $age[1] ?? null, preg_match('/(?:^| )(?:women|womens|ladies)(?: |$)| w$/', $name) === 1];
    }

    public static function team(string $name): string
    {
        $squad = self::squad($name);
        $name = self::words($name);
        do {
            $previous = $name;
            $name = preg_replace('/ (?:women|womens|ladies|w|u[0-9]{2}|iii|ii|b|c)$/', '', $name);
        } while ($name !== $previous);
        $name = trim(preg_replace('/^(?:fc|cf|afc) | (?:fc|cf|afc|calcio)$/', '', $name));
        $name = self::ALIASES[$name] ?? $name;
        if ($name === '') {
            return '';
        }

        return $name.($squad[0] === 'first' ? '' : ' '.$squad[0]).($squad[1] === null ? '' : ' u'.$squad[1]).($squad[2] ? ' women' : '');
    }

    public static function similarity(string $left, string $right): float
    {
        if (self::squad($left) !== self::squad($right)) {
            return 0;
        }
        $left = self::team($left);
        $right = self::team($right);
        if ($left === '' || $right === '') {
            return 0;
        }
        if ($left === $right) {
            return 100;
        }
        $qualifiers = ['city', 'united', 'town', 'rovers', 'wanderers', 'athletic', 'albion', 'hotspur', 'wednesday'];
        $leftWords = explode(' ', preg_replace('/(?: (?:women|u[0-9]{2}|b|c))+$/', '', $left));
        $rightWords = explode(' ', preg_replace('/(?: (?:women|u[0-9]{2}|b|c))+$/', '', $right));
        $leftQualifier = array_pop($leftWords);
        $rightQualifier = array_pop($rightWords);
        if ($leftQualifier !== $rightQualifier && $leftWords === $rightWords
            && in_array($leftQualifier, $qualifiers, true) && in_array($rightQualifier, $qualifiers, true)) {
            return 0;
        }
        similar_text($left, $right, $similarity);

        return $similarity;
    }

    public static function mentionScore(string $text, string $name): int
    {
        $text = ' '.self::words($text).' ';
        $identity = self::team($name);
        if ($identity === '') {
            return 0;
        }
        $forms = [$identity, self::words($name)];
        foreach (self::ALIASES as $alias => $canonical) {
            if ($canonical === $identity) {
                $forms[] = $alias;
            }
        }
        foreach (array_unique($forms) as $form) {
            if ($form !== '' && str_contains($text, ' '.$form.' ')) {
                return 100;
            }
        }
        $words = explode(' ', $identity);
        $prefix = $words[0];
        if (self::squad($name) === ['first', null, false] && count($words) >= 3 && strlen($prefix) >= 5
            && ! in_array($prefix, ['borussia', 'sporting', 'atletico', 'united', 'manchester', 'sheffield', 'bristol'], true)
            && str_contains($text, ' '.$prefix.' ')) {
            return 50;
        }

        return 0;
    }

    public static function league(string $name): string
    {
        $name = self::words($name);

        return trim(preg_replace('/^(?:english|england|italian|italy|german|germany|french|france) /', '', $name));
    }
}
