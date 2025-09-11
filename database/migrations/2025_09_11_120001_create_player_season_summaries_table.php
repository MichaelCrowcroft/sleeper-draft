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
        Schema::create('player_season_summaries', function (Blueprint $table) {
            $table->id();

            // Link to players via external player_id key used across stats tables
            $table->string('player_id')->index();
            $table->integer('season')->index();

            // Core summary metrics (mostly derived from Player::getSeason2024Summary())
            $table->float('total_points')->nullable();
            $table->float('min_points')->nullable();
            $table->float('max_points')->nullable();
            $table->float('average_points_per_game')->nullable();
            $table->float('stddev_below')->nullable();
            $table->float('stddev_above')->nullable();
            $table->integer('games_active')->nullable();
            $table->float('snap_percentage_avg')->nullable();
            $table->integer('position_rank')->nullable();

            // Additional cached metrics
            $table->float('target_share_avg')->nullable();

            // Complex aggregates as JSON (e.g., volatility metrics)
            $table->json('volatility')->nullable();

            $table->timestamps();

            $table->unique(['player_id', 'season']);
            $table->index(['season', 'position_rank']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('player_season_summaries');
    }
};
