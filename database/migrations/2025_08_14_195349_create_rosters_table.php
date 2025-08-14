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
        Schema::create('rosters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('league_id')->constrained('leagues')->cascadeOnDelete();
            $table->string('sleeper_roster_id');
            $table->string('owner_id')->nullable();
            $table->unsignedInteger('wins')->default(0);
            $table->unsignedInteger('losses')->default(0);
            $table->unsignedInteger('ties')->default(0);
            $table->float('fpts', 8, 2)->default(0);
            $table->float('fpts_decimal', 8, 2)->default(0);
            $table->json('players')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['league_id', 'sleeper_roster_id']);
            $table->index('sleeper_roster_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rosters');
    }
};
