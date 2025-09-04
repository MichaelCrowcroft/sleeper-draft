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
            $table->string('player_id');
            $table->date('game_date');
            $table->integer('season');
            $table->integer('week');
            $table->string('season_type');
            $table->string('sport')->nullable();
            $table->string('company')->nullable();
            $table->string('team')->nullable();
            $table->string('opponent')->nullable();
            $table->string('game_id')->nullable();
            $table->bigInteger('updated_at_ms')->nullable();
            $table->bigInteger('last_modified_ms')->nullable();

            $table->json('stats')->nullable();
            $table->json('raw')->nullable();

            // Indexes for performance (only add new ones)
            $table->index(['player_id', 'season', 'week']);
            $table->index('game_date');

            // Unique constraint to prevent duplicates
            $table->unique(['player_id', 'season', 'week', 'season_type'], 'unique_player_week_stats');
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
