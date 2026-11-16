<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pinnacle_betfair_events', function (Blueprint $table): void {
            $table->index(['sport', 'betfair_event_id']);
        });
        Schema::create('pinnacle_betfair_team_aliases', function (Blueprint $table): void {
            $table->string('sport', 32);
            $table->string('name_key', 64);
            $table->string('competition');
            $table->string('source_name');
            $table->string('betfair_name');
            $table->timestamps();
            $table->primary(['sport', 'name_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pinnacle_betfair_team_aliases');
        Schema::table('pinnacle_betfair_events', function (Blueprint $table): void {
            $table->dropIndex(['sport', 'betfair_event_id']);
        });
    }
};
