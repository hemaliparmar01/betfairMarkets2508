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
        Schema::create('pinnacle_drop_archives', function (Blueprint $table): void {
            $table->string('alert_key', 64)->primary();
            $table->unsignedBigInteger('alerted_at')->index();
            $table->string('sport', 32);
            $table->boolean('is_live');
            $table->unsignedTinyInteger('market_group');
            $table->unsignedBigInteger('start_timestamp')->nullable();
            $table->boolean('betfair_higher')->nullable();
            $table->json('payload');
            $table->index(['is_live', 'alerted_at', 'alert_key'], 'pinnacle_archive_page_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pinnacle_drop_archives');
    }
};
