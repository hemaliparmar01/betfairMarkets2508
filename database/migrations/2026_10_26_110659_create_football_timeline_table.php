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
        Schema::create('football_timeline', function (Blueprint $table) {
            $table->id();
            $table->string('match_id')->unique();
            $table->json('home_goals');
            $table->json('away_goals');
            $table->json('home_yellow_cards');
            $table->json('away_yellow_cards');
            $table->json('home_red_cards');
            $table->json('away_red_cards');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('football_timeline');
    }
};
