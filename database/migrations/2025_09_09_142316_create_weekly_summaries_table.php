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
        Schema::create('weekly_summaries', function (Blueprint $table) {
            $table->id();
            $table->string('league_id');
            $table->integer('year');
            $table->integer('week');
            $table->longText('content')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->longText('prompt_used')->nullable();
            $table->timestamps();

            // Add indexes for efficient lookups
            $table->unique(['league_id', 'year', 'week']);
            $table->index(['league_id', 'year']);
            $table->index(['generated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('weekly_summaries');
    }
};
