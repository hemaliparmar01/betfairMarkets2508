<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            DB::table('pinnacle_league_mappings')->where('sport', 'soccer')->where('country', 'Qatar')->delete();
            DB::table('pinnacle_league_mappings')->insert([
                ['sport' => 'soccer', 'provider_league_id' => 1857, 'provider_league_name' => 'Chile - Primera Division', 'country' => 'Chile', 'league' => 'Chilean Primera División'],
                // ['sport' => 'soccer', 'provider_league_id' => 2359, 'provider_league_name' => 'Paraguay - Division Profesional', 'country' => 'Paraguay', 'league' => 'Paraguayan Primera División'],
                ['sport' => 'soccer', 'provider_league_id' => 5593, 'provider_league_name' => 'Uruguay - Primera Division', 'country' => 'Uruguay', 'league' => 'Uruguayan Primera División'],
            ]);
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            DB::table('pinnacle_league_mappings')->where('sport', 'soccer')
                ->whereIn('provider_league_id', [1857, 2359, 5593])->delete();
            DB::table('pinnacle_league_mappings')->insert([
                'sport' => 'soccer', 'provider_league_id' => 8128,
                'provider_league_name' => 'Qatar - Stars League', 'country' => 'Qatar', 'league' => 'Qatar Stars League',
            ]);
        });
    }
};
