<?php

namespace App\Providers;

use App\Events\FootballSportsScoreUpdated;
use App\Jobs\ProcessAlert;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->shareCheckAuthSubscription();
        View::composer(['users.layout.main', 'admin.dashboard.index'], static function ($view): void {
            $branding = request()->attributes->get('branding');
            if ($branding === null) {
                $paths = Schema::hasTable('site_settings')
                    ? DB::table('site_settings')->whereIn('key', array_keys(config('app.branding_assets')))->pluck('value', 'key')->all()
                    : [];
                $defaults = ['logo' => asset('logo.png'), 'favicon' => asset('favicon.png')];
                $assets = [];
                foreach (config('app.branding_assets') as $key => $label) {
                    $fallback = $defaults[explode('_', $key)[0]];
                    $path = $paths[$key] ?? null;
                    $assets[$key] = $path && Storage::disk('public')->exists($path)
                        ? Storage::disk('public')->url($path) : $fallback;
                }
                $branding = ['assets' => $assets, 'defaults' => $defaults];
                request()->attributes->set('branding', $branding);
            }
            $view->with('branding', $branding);
        });
        View::composer('users.layout.main', static function ($view): void {
            $route = request()->route()?->getName();
            $page = config('app.seo_pages')[$route] ?? null;
            $fixtureSport = match ($route) {
                'tennis.home' => 'Tennis', 'basketball.home' => 'Basketball', 'cricket.home' => 'Cricket',
                'icehockey.home' => 'Ice Hockey', 'americanfootball.home' => 'American Football',
                'baseball.home' => 'Baseball', 'horseracing.home' => 'Horse Racing', 'greyhounds.home' => 'Greyhounds',
                default => null,
            };
            if ($fixtureSport !== null) {
                $page = [
                    'title' => __(':sport Fixtures & Odds | BF Markets Analytics', ['sport' => __($fixtureSport)]),
                    'description' => __('Monitor live and upcoming :sport fixtures with real-time scores, odds movements, statistics and market information.', ['sport' => __($fixtureSport)]),
                    'index' => ! in_array($route, ['horseracing.home', 'greyhounds.home'], true),
                ];
            }
            $canonical = url(request()->path());
            $index = $page !== null && ($page['index'] ?? true);
            foreach (array_keys(request()->query()) as $parameter) {
                if (! str_starts_with($parameter, 'utm_') && ! in_array($parameter, ['gclid', 'fbclid'], true)) {
                    $index = false;
                }
            }
            $seo = [
                'title' => isset($page['title']) ? __($page['title']) : null,
                'description' => isset($page['description']) ? __($page['description']) : null,
                'canonical' => $canonical,
                'robots' => $index ? 'index, follow' : 'noindex, follow',
                'image' => $view->getData()['branding']['assets']['logo_'.app()->getLocale().'_light'] ?? asset('logo.png'),
                'schema' => [],
            ];
            if ($route === 'football.home') {
                $seo['schema'] = [
                    ['@type' => 'Organization', '@id' => $canonical.'#organization', 'name' => 'BF Markets Analytics', 'url' => $canonical, 'logo' => $seo['image'], 'parentOrganization' => ['@type' => 'Organization', 'name' => 'Digital Analytics Group LLC']],
                    ['@type' => 'WebSite', 'name' => 'BF Markets Analytics', 'url' => $canonical, 'inLanguage' => app()->getLocale(), 'publisher' => ['@id' => $canonical.'#organization']],
                    ['@type' => 'SoftwareApplication', 'name' => 'BF Markets Analytics', 'applicationCategory' => 'BusinessApplication', 'operatingSystem' => 'Web', 'description' => __('Real-time sports exchange market analytics and market data platform.'), 'brand' => ['@type' => 'Brand', 'name' => 'BF Markets Analytics'], 'url' => $canonical],
                ];
            } elseif ($page !== null && ($page['index'] ?? true)) {
                $seo['schema'][] = ['@type' => 'BreadcrumbList', 'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => __('Fixtures'), 'item' => route('football.home')],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => trim(view()->yieldContent('title')), 'item' => $canonical],
                ]];
            }
            if (isset($page['faq'])) {
                $questions = [];
                foreach ($page['faq'] as $question => $answer) {
                    $questions[] = ['@type' => 'Question', 'name' => __($question), 'acceptedAnswer' => ['@type' => 'Answer', 'text' => __($answer)]];
                }
                $seo['schema'][] = ['@type' => 'FAQPage', 'mainEntity' => $questions];
            }
            if ($seo['schema'] !== []) {
                $seo['schema'] = ['@context' => 'https://schema.org', '@graph' => $seo['schema']];
            }
            $view->with('seo', $seo);
        });
        View::composer([
            'users.sports.football.footballDetails', 'users.sports.cricket.cricketDetails',
            'users.sports.icehockey.icehockeyDetails', 'users.sports.americanfootball.americanfootballDetails',
            'users.sports.baseball.baseballDetails', 'users.sports.basketball.basketballDetails',
            'users.sports.tennis.tennisDetails', 'users.sports.horseracing.horseracingDetails',
            'users.sports.greyhounds.greyhoundsDetails',
        ], static function ($view): void {
            $groups = $view->getData()['data'] ?? [];
            $known = request()->input('_fixture_versions', []);
            if (is_string($known)) {
                $known = strlen($known) <= 131072 ? json_decode($known, true) : [];
            }
            $known = is_array($known) && count($known) <= 2000 ? $known : [];
            $access = $view->getData()['checkAuthAndSubscription'] ?? checkAuthAndSubscription();
            $timeVersion = in_array(request()->input('type'), ['prematch_drops', 'market_shock'], true) ? time() : intdiv(time(), 60);
            $context = [$view->name(), app()->getLocale(), getCurrentUserTimezone(), auth('customers')->id(), $access, request()->input('type'), request()->input('market_name'), $timeVersion];
            foreach ($groups as $groupKey => &$group) {
                foreach ($group['matches'] as $index => &$match) {
                    $key = hash('xxh3', serialize([$view->name(), $groupKey, $match['unique_key'] ?? $match['sportsApiPro_match_id'] ?? $match['betfair_match_id'] ?? $index]));
                    $version = hash('xxh3', serialize([$context, $match]));
                    $match['_fixture_key'] = $key;
                    $match['_fixture_version'] = $version;
                    $match['_fixture_reuse'] = ! request()->boolean('append') && ($known[$key] ?? null) === $version;
                }
                unset($match);
            }
            unset($group);
            $view->with('data', $groups);
        });

        View::composer(['users.layout.main', 'admin.layouts.main', 'admin.auth.login'], static function ($view): void {
            $view->with('uiTranslations', collect([
                ' Goals',
                '1st Half',
                '1st Half Draw Odds',
                '1st Quarter',
                '1st half',
                '1st quarter',
                '2 pointers',
                '2nd Quarter',
                '2nd half',
                '2nd quarter',
                '3 pointers',
                '3rd Quarter',
                '3rd quarter',
                '4th Quarter',
                '4th quarter',
                'Abandoned',
                'Accurate passes',
                'Aces',
                'Active',
                'Are you sure you want to delete this plan?',
                'Asian Handicap',
                'Assists',
                'Attack',
                'Available Liquidity Distribution',
                'Average rating',
                'Away',
                'Back',
                'Back heavy above 58%; Lay heavy below 42%.',
                'Back odds history',
                'Back ↑',
                'Balance',
                'Balanced →',
                'Ball possession',
                'Bet against the event',
                'Bet in favor of the event',
                'Betfair Back / Lay',
                'Betfair Edge',
                'Betfair Sizes',
                'Big chances',
                'Blocked shots',
                'Blocks',
                'Both Teams to Score',
                'Break points',
                'Break points converted',
                'Break points saved',
                'Cancelled',
                'Change in the favorite runner’s back size: strong above 10%, directional above 3%.',
                'Clearances',
                'Close',
                'Close menu',
                'Connect Telegram',
                'Connecting',
                'Connecting...',
                'Corner kicks',
                'Correct Score',
                'Current point:',
                'Defence',
                'Defense',
                'Delayed',
                'Delta',
                'Disable platform pop-up alerts? Telegram preferences will stay unchanged.',
                'Disconnect Telegram',
                'Disconnected',
                'Disconnecting...',
                'Don\'t show again',
                'Double Chance',
                'Double faults',
                'Drop',
                'Duels',
                'Earlier movements may be missing from the previously limited history.',
                'Edge',
                'Edge %',
                'Edge % represents the percentage difference between the current odds (Pinnacle or Betfair) and the fair NVP odds. A positive (+) Edge % means you are getting better odds than the fair price (Value Bet). A negative (-) Edge % means the odds are lower than the fair price.',
                'Expected goals (xG)',
                'Expired',
                'Expires in',
                'FT',
                'Favorite',
                'Favorite update failed:',
                'Feed connected. Prices and available sizes update when the provider sends a change.',
                'Field goals',
                'Finished',
                'First Half',
                'First half',
                'First price observed during monitoring',
                'First serve',
                'First serve points',
                'Fouls',
                'Free kicks',
                'Free throws',
                'Full Market',
                'Full Match',
                'Full match',
                'Games won',
                'Goalkeeper saves',
                'Goalkeeping',
                'Goals',
                'HT',
                'Half Time',
                'Halftime',
                'High',
                'Home',
                'How fast Back Size changes',
                'How unpredictable the market is',
                'Inactive',
                'Indicators',
                'Initial',
                'Interceptions',
                'Interrupted',
                'Lay',
                'Lay ↓',
                'Liquidity',
                'Liquidity Distribution',
                'Liquidity-supported opening',
                'Live',
                'Live Match Statistics',
                'Live Statistics',
                'Live connection unavailable. Displaying the last received values.',
                'Loading matches…',
                'Loading races…',
                'Login successful.',
                'Login with an active subscription to view odds.',
                'Logout successful.',
                'Low',
                'Market Details',
                'Market activity filter request failed:',
                'Market activity request failed:',
                'Market before event starts',
                'Market data is not available yet.',
                'Match / Market',
                'Match Odds',
                'Match Overview',
                'Match Predictions',
                'Match Winner',
                'Match detail refresh failed:',
                'Match markets &amp; charts →',
                'Match overview',
                'Matches',
                'Medium password',
                'Momentum',
                'Money available at best price',
                'Moneyline',
                'NVP (No-Vig Price) is the \'fair\' or \'true\' odds for an outcome. It is calculated by removing the bookmaker\'s margin (vigorish) from the Pinnacle odds. It represents the true probability of the event without the bookmaker\'s built-in profit.',
                'New Score',
                'No data available.',
                'No innings data available.',
                'No odds history available.',
                'No pressure',
                'No runner data available.',
                'No statistics available for this period yet.',
                'No statistics available for this section.',
                'Not started',
                'Notification preferences saved successfully.',
                'Notification request failed.',
                'Odds',
                'Odds history',
                'Offsides',
                'Open @BFMarketsAlertsBot and press Start to finish connecting.',
                'Open menu',
                'Other Stats',
                'Over',
                'Over/Under',
                'Overtime',
                'Participant in the market',
                'Participant with the lowest Back Odds',
                'Passes',
                'Pending',
                'Percentage change in the current favorite runner’s back size.',
                'Period',
                'Pinnacle Edge',
                'Pinnacle Odds',
                'Pinnacle odds drops, grouped by match',
                'Pinnacle:',
                'Points',
                'Points won',
                'Postponed',
                'Prematch',
                'Price',
                'Provider updates unavailable or delayed. Displaying the last received values.',
                'Quarter',
                'Race Details',
                'Race Finished',
                'Race Predictions',
                'Race Started',
                'Race Winner',
                'Race details and odds become available five minutes before the start.',
                'Race markets &amp; charts →',
                'Rebounds',
                'Recent Alerts',
                'Red cards',
                'Request failed:',
                'Return',
                'Return games won',
                'Runner',
                'Saving...',
                'Score',
                'Scoring',
                'Second half',
                'Second serve points',
                'Serve',
                'Service',
                'Service games won',
                'Serving',
                'Sets won',
                'Shots',
                'Shots off target',
                'Shots on target',
                'Size',
                'Size delta',
                'Size momentum',
                'Sizes and liquidity indicators describe available offers, not matched trades. Traded volume is not supplied by this feed.',
                'Sizes are available offers, not matched trades. Traded volume is not supplied by this feed.',
                'Standard deviation of log odds returns over the latest ten seconds.',
                'Statistics periods',
                'Steals',
                'Strong password',
                'Suspended',
                'Tackles',
                'Telegram connected successfully.',
                'Telegram connection token expired. Please connect again.',
                'Telegram disconnected. Your preferences are saved.',
                'Throw-ins',
                'Time',
                'Total money in the market',
                'Total passes',
                'Total points won',
                'Total score',
                'Total shots',
                'Turnovers',
                'Unable to load matches. Please try again.',
                'Unable to load races. Please try again.',
                'Unable to refresh race details.',
                'Unable to save favorite. Please try again.',
                'Under',
                'Use at least 8 characters with letters, numbers, and symbols.',
                'Verify your BF Markets account',
                'Volatility',
                'Voting closes at the off time.',
                'Voting closes when the match starts.',
                'Waiting for the provider feed.',
                'Walkover',
                'Weak password',
                'Where the money is moving',
                'Where the money is now',
                'Who will win this match?',
                'Who will win this race?',
                'Wins',
                'Yellow cards',
                'Your BF Markets login code',
                'Your BF Markets verification code is :code. It expires in 10 minutes.',
                'Your login code has been sent via :channel.',
                'Your prediction has been recorded.',
                'away',
                'draw',
                'h ago',
                'home',
                'm ago',
                'min',
                'moneyline',
                'no',
                'over',
                's ago',
                'under',
                'yes',
                '· Errors:',
                'Δ Delta',
                '— (fair price pending)',
                '↓ Scroll for more matches ↓',
                '↓ Scroll for more races ↓',
                '▲ UPCOMING',
                '● LIVE',
                '⬆ LAY',
                '⬇ BACK',
                '🔒 Log in with an active subscription to view race odds.',
                '🥇 1st Favourite',
                '🥈 2nd Favourite',
                '🥉 3rd Favourite',
            ])->mapWithKeys(static fn (string $key): array => [$key => __($key)])->all());
        });
        Event::listen(FootballSportsScoreUpdated::class, static function (): void {
            if (config('services.telegram.enabled')) {
                ProcessAlert::dispatch()->afterCommit();
            }
        });
    }

    private function shareCheckAuthSubscription(): void
    {
        View::composer('*', fn ($view) => $view->with('checkAuthAndSubscription', checkAuthAndSubscription())
        );
    }
}
