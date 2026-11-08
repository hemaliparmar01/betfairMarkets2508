<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pinnacle_league_mappings', function (Blueprint $table): void {
            $table->id();
            $table->string('sport', 16);
            $table->unsignedBigInteger('provider_league_id')->nullable();
            $table->string('provider_league_name')->nullable();
            $table->string('country');
            $table->string('league');
            $table->unique(['sport', 'provider_league_id']);
        });

        $mappings = [
            ['soccer', 2081, 'Greece - Super League', 'Greece', 'Super League Greece'],
            ['soccer', 1980, 'England - Premier League', 'England', 'Premier League'],
            ['soccer', 2196, 'Spain - La Liga', 'Spain', 'La Liga'],
            ['soccer', 2436, 'Italy - Serie A', 'Italy', 'Serie A'],
            ['soccer', 2036, 'France - Ligue 1', 'France', 'Ligue 1'],
            ['soccer', 2421, 'Scotland - Premiership', 'Scotland', 'Scottish Premiership'],
            ['soccer', 1928, 'Netherlands - Eredivisie', 'Netherlands', 'Eredivisie'],
            ['soccer', 1792, 'Austria - Bundesliga', 'Austria', 'Austrian Bundesliga'],
            ['soccer', 1842, 'Germany - Bundesliga', 'Germany', 'Bundesliga'],
            ['soccer', 1957, 'England - League 1', 'England', 'EFL League One'],
            ['soccer', 1958, 'England - League 2', 'England', 'EFL League Two'],
            ['soccer', 1817, 'Belgium - Pro League', 'Belgium', 'Belgian Pro League'],
            ['soccer', 2333, 'Norway - Eliteserien', 'Norway', 'Eliteserien'],
            ['soccer', 1844, 'Germany - 3. Liga', 'Germany', '3. Liga'],
            ['soccer', 2592, 'Turkey - Super League', 'Turkey', 'Süper Lig'],
            ['soccer', 2518, 'Switzerland - Challenge League', 'Switzerland', 'Swiss Challenge League'],
            ['soccer', 6633, 'Poland - 1st Liga', 'Poland', 'I Liga'],
            ['soccer', 1891, 'Czech Republic - First Liga', 'Czech Republic', 'Czech First League'],
            ['soccer', 2438, 'Italy - Serie B', 'Italy', 'Serie B'],
            ['soccer', 2386, 'Portugal - Primeira Liga', 'Portugal', 'Primeira Liga'],
            ['soccer', 1880, 'Croatia - HNL', 'Croatia', 'Croatian HNL'],
            ['soccer', 200719, 'UEFA - Nations League A', 'UEFA', 'UEFA Nations League'],
            ['soccer', 200721, 'UEFA - Nations League B', 'UEFA', 'UEFA Nations League'],
            ['soccer', 2242, 'Mexico - Liga MX', 'Mexico', 'Liga MX'],
            ['soccer', 8126, 'UAE - Pro League', 'UAE', 'UAE Pro League'],
            ['soccer', 1728, 'Sweden - Allsvenskan', 'Sweden', 'Allsvenskan'],
            ['soccer', 2517, 'Switzerland - Super League', 'Switzerland', 'Swiss Super League'],
            ['soccer', 9441, 'Czech Republic - FNL', 'Czech Republic', 'Czech National Football League'],
            ['soccer', 217399, 'Italy - Serie C Group C', 'Italy', 'Serie C'],
            ['soccer', 217401, 'Italy - Serie C Group A', 'Italy', 'Serie C'],
            ['soccer', 1913, 'Denmark - Superliga', 'Denmark', 'Danish Superliga'],
            ['soccer', 2395, 'Romania - Liga 1', 'Romania', 'Romanian SuperLiga'],
            ['soccer', 1929, 'Netherlands - Eerste Divisie', 'Netherlands', 'Eerste Divisie'],
            ['soccer', 217400, 'Italy - Serie C Group B', 'Italy', 'Serie C'],
            ['soccer', 2374, 'Poland - Ekstraklasa', 'Poland', 'Ekstraklasa'],
            ['soccer', 206162, 'Serbia - Prva Liga', 'Serbia', 'Serbian First League'],
            ['soccer', 8128, 'Qatar - Stars League', 'Qatar', 'Qatar Stars League'],
            ['soccer', 210697, 'Argentina - Liga Pro', 'Argentina', 'Argentine Primera División'],
            ['soccer', 1818, 'Belgium - Challenger Pro League', 'Belgium', 'Challenger Pro League'],
            ['soccer', 2432, 'Spain - Segunda Division', 'Spain', 'LaLiga 2'],
            ['soccer', 1834, 'Brazil - Serie A', 'Brazil', 'Brasileirão Série A'],
            ['soccer', 1766, 'Australia - A League', 'Australia', 'A-League Men'],
            ['soccer', 2253, 'Morocco - Botola Pro', 'Morocco', 'Botola Pro'],
            ['soccer', 206711, 'Greece - Super League 2', 'Greece', 'Super League 2'],
            ['soccer', 2650, 'Ukraine - Premier League', 'Ukraine', 'Ukrainian Premier League'],
            ['soccer', 2434, 'Serbia - Super Liga', 'Serbia', 'Serbian SuperLiga'],
            ['soccer', 2136, 'Israel - Premier League', 'Israel', 'Israeli Premier League'],
            ['soccer', 2387, 'Portugal - Liga 2', 'Portugal', 'Liga Portugal 2'],
            ['soccer', 1904, 'Denmark - Division 1', 'Denmark', 'Danish 1st Division'],
            ['soccer', 293799, 'France - National 1', 'France', 'Championnat National'],
            ['soccer', 2627, 'UEFA - Champions League', 'UEFA', 'UEFA Champions League'],
            ['soccer', 200726, 'UEFA - Nations League C', 'UEFA', 'UEFA Nations League'],
            ['soccer', 2630, 'UEFA - Europa League', 'UEFA', 'UEFA Europa League'],
            ['soccer', 2417, 'Scotland - Championship', 'Scotland', 'Scottish Championship'],
            ['soccer', 1977, 'England - Championship', 'England', 'EFL Championship'],
            ['tennis', 296132, 'WTA Finals Indian Wells - Doubles', 'Year-End Championships', 'WTA Finals'],
            ['tennis', 301693, 'WTA Finals Indian Wells - Groups', 'Year-End Championships', 'WTA Finals'],
            ['tennis', 221995, 'WTA Chennai - Final', 'WTA 250', 'Chennai Open'],
            ['tennis', 10199, 'ATP Challenger Bratislava - Final', 'ATP Challenger', 'Bratislava Challenger'],
            ['tennis', 3625, 'ATP Stockholm - R1', 'ATP 250', 'Nordic Open – Stockholm'],
            ['tennis', 4565, 'ATP Stockholm - Doubles', 'ATP 250', 'Nordic Open – Stockholm'],
            ['tennis', 4562, 'ATP Stockholm - Qualifiers', 'ATP 250', 'Nordic Open – Stockholm'],
            ['tennis', 2955, 'ATP Paris - Final', 'ATP Masters 1000', 'Paris Masters'],
            ['soccer', null, null, 'FIFA', 'FIFA World Cup'],
            ['soccer', null, null, 'FIFA', 'FIFA World Cup Qualifiers'],
            ['soccer', null, null, 'FIFA', 'FIFA Club World Cup'],
            ['soccer', null, null, 'FIFA', 'Other Major FIFA International Competitions'],
            ['soccer', null, null, 'UEFA', 'UEFA European Championship (EURO)'],
            ['soccer', null, null, 'UEFA', 'EURO Qualifiers'],
            ['soccer', null, null, 'UEFA', 'UEFA Champions League Qualifiers'],
            ['soccer', null, null, 'UEFA', 'UEFA Europa League Qualifiers'],
            ['soccer', null, null, 'UEFA', 'UEFA Conference League'],
            ['soccer', null, null, 'UEFA', 'UEFA Conference League Qualifiers'],
            ['soccer', null, null, 'UEFA', 'UEFA Super Cup'],
            ['soccer', null, null, 'Germany', '2. Bundesliga'],
            ['soccer', null, null, 'France', 'Ligue 2'],
            ['soccer', null, null, 'Austria', '2. Liga'],
            ['soccer', null, null, 'Norway', 'OBOS-ligaen'],
            ['soccer', null, null, 'Sweden', 'Superettan'],
            ['soccer', null, null, 'Croatia', 'Croatian First Football League'],
            ['soccer', null, null, 'USA / Canada', 'Major League Soccer (MLS)'],
            ['soccer', null, null, 'Saudi Arabia', 'Saudi Pro League'],
            ['soccer', null, null, 'Japan', 'J1 League'],
            ['soccer', null, null, 'South Korea', 'K League 1'],
            ['soccer', null, null, 'China', 'Chinese Super League'],
            ['soccer', null, null, 'South Africa', 'South African Premier Division'],
            ['soccer', null, null, 'Egypt', 'Egyptian Premier League'],
        ];
        DB::table('pinnacle_league_mappings')->insert(array_map(
            static fn (array $mapping): array => array_combine(
                ['sport', 'provider_league_id', 'provider_league_name', 'country', 'league'],
                $mapping,
            ),
            $mappings,
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('pinnacle_league_mappings');
    }
};
