<?php

namespace App\Services;

use InvalidArgumentException;
use Locale;

class SportsMatchCache extends MatchRecordCache
{
    public const SPORTS = ['Cricket' => 'cricket', 'IceHockey' => 'icehockey', 'AmericanFootball' => 'americanfootball',
        'Baseball' => 'baseball', 'HorseRacing' => 'horseracing', 'Greyhounds' => 'greyhounds'];

    protected bool $preserveNewerProviderState = false;

    public function __construct(private readonly string $sportPrefix)
    {
        if (! isset(self::SPORTS[$sportPrefix])) {
            throw new InvalidArgumentException('Unsupported individual match cache: '.$sportPrefix);
        }
        parent::__construct();
    }

    public static function for(string $prefix): self
    {
        $binding = self::class.':'.$prefix;
        if (! app()->bound($binding)) {
            app()->singleton($binding, fn (): self => new self($prefix));
        }

        return app($binding);
    }

    protected function prefix(): string
    {
        return $this->sportPrefix;
    }

    protected function sport(): string
    {
        return self::SPORTS[$this->sportPrefix];
    }

    protected function catalogKey(): string
    {
        return $this->prefix().':matches:catalog';
    }

    protected function idsKey(): string
    {
        return $this->prefix().':matches:ids';
    }

    protected function readyKey(): string
    {
        return $this->prefix().':matches:individual';
    }

    protected function allowed(array $match): bool
    {
        return true;
    }

    protected function canonicalMatches(array $matches): array
    {
        return $matches;
    }

    protected function persistTimeline(array &$match, array $previous): void {}

    protected function entry(string $key, array $match, string $encoded, int $expires): array
    {
        $version = hash('xxh3', $encoded);
        $entry = array_diff_key($match, ['markets' => true, 'timeline' => true]);
        $entry['_fixture_cache_key'] = $key;
        $entry['_fixture_priority'] = 0;
        $entry['_fixture_source_version'] = ['odds' => $version, 'timeline' => $version];
        $entry['_expires_at'] = $expires;
        $entry['_payload_bytes'] = strlen($encoded);
        $entry['_cache_updated_at'] = microtime(true);
        $entry['_market_activity'] = prepareMatchOddsMonitoring($match, marketActivitySummary: true);
        $entry['_market_options'] = [];
        foreach ($match['markets'] ?? [] as $runners) {
            $runner = $runners[array_key_first($runners)] ?? [];
            if (isset($runner['marketType'])) {
                $type = $runner['marketType'];
                $entry['_market_options'][$type] ??= ['value' => $type, 'label' => $runner['name'] ?? str_replace('_', ' ', $type), 'oddsCount' => count($runners)];
            }
        }
        if (in_array($this->sport(), ['horseracing', 'greyhounds'], true)) {
            $entry['_sort_markets'] = array_map(static fn (array $runners): array => array_map(
                static fn (array $runner): array => array_diff_key($runner, ['backOddsHistory' => true, 'movementHistory' => true, 'recentOddsHistory' => true]), $runners), $match['markets'] ?? []);
        }

        return $entry;
    }

    public function store(array $matches, int $ttlMinutes, bool $mergeLifecycle, ?array $existing = null): void
    {
        $field = in_array($this->sport(), ['horseracing', 'greyhounds'], true) ? 'country' : 'tournament_country';
        foreach ($matches as $key => &$match) {
            $country = array_find([$match[$field] ?? null, $match['country'] ?? null, $match['country_code'] ?? null,
                $match['countryCode'] ?? null, $existing[$key][$field] ?? null, $existing[$key]['country'] ?? null],
                static fn (mixed $value): bool => is_array($value) || (is_string($value) && trim($value) !== ''));
            if (is_array($country)) {
                $country = $country['name'] ?? $country['code'] ?? $country['alpha2'] ?? null;
            }
            if (is_string($country) && trim($country) !== '') {
                $country = trim($country);
                $match[$field] = preg_match('/^[a-z]{2}$/i', $country) && class_exists(Locale::class)
                    ? Locale::getDisplayRegion('und_'.strtoupper($country), 'en') : $country;
            }
        }
        unset($match);
        parent::store($matches, $ttlMinutes, $mergeLifecycle, $existing);
    }
}
