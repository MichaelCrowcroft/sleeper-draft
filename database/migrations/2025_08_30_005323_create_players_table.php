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
        Schema::create('players', function (Blueprint $table) {
            $table->id();

            // Core identifiers
            $table->string('player_id')->unique();
            $table->string('sport', 16)->default('nfl');

            // Names and search helpers
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('full_name')->nullable()->index();
            $table->string('search_first_name')->nullable();
            $table->string('search_last_name')->nullable();
            $table->string('search_full_name')->nullable();
            $table->integer('search_rank')->nullable();

            // Stats
            $table->float('adp')->nullable();

            // Team / position
            $table->string('team', 10)->nullable()->index();
            $table->string('position', 10)->nullable()->index();
            $table->json('fantasy_positions')->nullable();

            // Status / activity
            $table->string('status', 32)->nullable()->index();
            $table->boolean('active')->nullable()->index();

            // Player details
            $table->unsignedInteger('number')->nullable();
            $table->unsignedInteger('age')->nullable();
            $table->unsignedInteger('years_exp')->nullable();
            $table->string('college')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('birth_city')->nullable();
            $table->string('birth_state')->nullable();
            $table->string('birth_country')->nullable();
            $table->string('height')->nullable();
            $table->unsignedInteger('weight')->nullable();

            // Depth chart
            $table->string('depth_chart_position', 10)->nullable();
            $table->unsignedInteger('depth_chart_order')->nullable();

            // Injuries
            $table->string('injury_status')->nullable();
            $table->string('injury_body_part')->nullable();
            $table->date('injury_start_date')->nullable();
            $table->text('injury_notes')->nullable();

            // Misc
            $table->unsignedBigInteger('news_updated')->nullable();
            $table->string('hashtag')->nullable();

            // External IDs (nullable strings to cover mixed formats)
            $table->string('espn_id')->nullable();
            $table->string('yahoo_id')->nullable();
            $table->string('rotowire_id')->nullable();
            $table->string('pff_id')->nullable();
            $table->string('sportradar_id')->nullable();
            $table->string('fantasy_data_id')->nullable();
            $table->string('gsis_id')->nullable();

            // Full raw payload to ensure we store all Sleeper fields
            $table->json('raw')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('players');
    }
};
