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
        Schema::create('player_projections', function (Blueprint $table) {
            $table->id();

            // Relations
            $table->foreignId('player_db_id')
                ->constrained('players')
                ->cascadeOnDelete();

            // Identity
            $table->string('player_id')->index(); // Sleeper player_id for convenience
            $table->string('sport', 16)->default('nfl')->index();
            $table->string('season', 8)->index();
            $table->unsignedTinyInteger('week')->index();
            $table->string('season_type', 16)->default('regular')->index();

            // Game/opponent metadata
            $table->date('date')->nullable();
            $table->string('team', 10)->nullable()->index();
            $table->string('opponent', 10)->nullable()->index();
            $table->string('game_id')->nullable();
            $table->string('company')->nullable(); // e.g. rotowire

            // Payloads
            $table->json('stats')->nullable();
            $table->json('raw')->nullable();

            // Timestamps from API
            $table->unsignedBigInteger('updated_at_ms')->nullable();
            $table->unsignedBigInteger('last_modified_ms')->nullable();

            $table->timestamps();

            // Uniqueness per player per season/week/source
            $table->unique(['player_id', 'season', 'week', 'season_type', 'company', 'sport'], 'uniq_proj_player_week_source');
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
        Schema::dropIfExists('player_projections');
    }
};
