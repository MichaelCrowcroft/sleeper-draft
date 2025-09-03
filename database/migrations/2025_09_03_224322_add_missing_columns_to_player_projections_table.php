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
        Schema::table('player_projections', function (Blueprint $table) {
            // Add missing columns
            $table->json('raw')->nullable()->after('game_id');
            $table->unsignedBigInteger('updated_at_ms')->nullable()->after('raw');
            $table->unsignedBigInteger('last_modified_ms')->nullable()->after('updated_at_ms');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('player_projections', function (Blueprint $table) {
            // Remove added columns
            $table->dropColumn(['raw', 'updated_at_ms', 'last_modified_ms']);
        });
    }
};
