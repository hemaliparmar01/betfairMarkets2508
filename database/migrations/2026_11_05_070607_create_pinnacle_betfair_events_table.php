<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pinnacle_betfair_events', function (Blueprint $table) {
            $table->string('sport', 32);
            $table->string('pinnacle_event_id', 32);
            $table->string('betfair_event_id', 32);
            $table->primary(['sport', 'pinnacle_event_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pinnacle_betfair_events');
    }
};
