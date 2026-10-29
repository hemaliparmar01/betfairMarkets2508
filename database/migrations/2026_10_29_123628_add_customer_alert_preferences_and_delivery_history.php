<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->string('telegram_chat_id', 32)->nullable()->unique();
            $table->string('telegram_username', 64)->nullable();
            $table->boolean('telegram_available')->default(false);
            $table->string('telegram_error', 160)->nullable();
            $table->string('telegram_token_hash', 64)->nullable()->unique();
            $table->timestamp('telegram_token_expires_at')->nullable();
            $table->boolean('popup_enabled')->default(true)->index();
            foreach (['ht00', '00_70', 'fav_losing', 'ltd', 'prematch_drops', 'market_shock'] as $type) {
                $table->boolean('tg_'.$type)->default(false);
                $table->index(['tg_'.$type, 'telegram_available'], 'customers_alert_'.$type.'_index');
            }
        });
        Schema::create('alerts', function (Blueprint $table): void {
            $table->id();
            $table->string('event_key', 64)->unique();
            $table->string('type', 32)->index();
            $table->string('match_id', 64)->nullable()->index();
            $table->foreignId('parent_alert_id')->nullable()->constrained('alerts');
            $table->json('payload');
            $table->timestamp('created_at');
            $table->timestamp('fanned_out_at')->nullable()->index();
        });
        Schema::create('alert_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('alert_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 16);
            $table->string('destination', 32)->nullable();
            $table->string('status', 16)->default('pending');
            $table->string('error', 160)->nullable();
            $table->string('remote_id', 64)->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
            $table->unique(['alert_id', 'customer_id', 'channel']);
            $table->index(['status', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_deliveries');
        Schema::dropIfExists('alerts');
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropUnique(['telegram_chat_id']);
            $table->dropUnique(['telegram_token_hash']);
            $table->dropIndex(['popup_enabled']);
            foreach (['ht00', '00_70', 'fav_losing', 'ltd', 'prematch_drops', 'market_shock'] as $type) {
                $table->dropIndex('customers_alert_'.$type.'_index');
                $table->dropColumn('tg_'.$type);
            }
            $table->dropColumn(['telegram_chat_id', 'telegram_username', 'telegram_available', 'telegram_error', 'telegram_token_hash', 'telegram_token_expires_at', 'popup_enabled']);
        });
    }
};
