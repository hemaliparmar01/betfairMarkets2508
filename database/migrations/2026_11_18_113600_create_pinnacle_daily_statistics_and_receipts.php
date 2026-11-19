<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pinnacle_daily_statistics', function (Blueprint $table): void {
            $table->unsignedBigInteger('day_start');
            $table->string('sport', 32);
            $table->unsignedBigInteger('total')->default(0);
            $table->unsignedBigInteger('compared')->default(0);
            $table->unsignedBigInteger('higher')->default(0);
            $table->primary(['day_start', 'sport']);
        });
        Schema::create('pinnacle_drop_receipts', function (Blueprint $table): void {
            $table->string('alert_key', 64)->primary();
            $table->unsignedBigInteger('alerted_at')->index();
            $table->string('batch_key', 32)->index();
        });
        Schema::table('pinnacle_drop_archives', function (Blueprint $table): void {
            $table->unsignedBigInteger('expires_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('pinnacle_drop_archives', function (Blueprint $table): void {
            $table->dropColumn('expires_at');
        });
        Schema::dropIfExists('pinnacle_drop_receipts');
        Schema::dropIfExists('pinnacle_daily_statistics');
    }
};
