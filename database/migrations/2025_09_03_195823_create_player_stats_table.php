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
        Schema::create('player_stats', function (Blueprint $table) {
            $table->id();

            $table->string('player_id')->index(); // Sleeper player_id for convenience
            $table->string('sport', 16)->default('nfl')->index();
            $table->string('season', 8)->index(); // e.g. "2024"
            $table->unsignedTinyInteger('week')->index(); // 1-18
            $table->string('season_type', 16)->default('regular')->index(); // regular/post

            // Game/opponent metadata
            $table->date('date')->nullable();
            $table->string('team', 10)->nullable()->index();
            $table->string('opponent', 10)->nullable()->index();
            $table->string('game_id')->nullable();
            $table->string('company')->nullable(); // e.g. sportradar

            // Payloads
            $table->json('stats')->nullable();
            $table->json('raw')->nullable(); // full item if we want to keep extra fields

            // Timestamps from API
            $table->unsignedBigInteger('updated_at_ms')->nullable();
            $table->unsignedBigInteger('last_modified_ms')->nullable();

            $table->timestamps();

            // Uniqueness per player per season/week/source
            $table->unique(['player_id', 'season', 'week', 'season_type', 'company', 'sport'], 'uniq_player_week_source');
            $table->foreign('player_id')
                ->references('player_id')
                ->on('players')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('player_stats');
    }
};
