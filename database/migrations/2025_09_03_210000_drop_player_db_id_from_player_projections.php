<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('player_projections', 'player_db_id')) {
            Schema::table('player_projections', function (Blueprint $table) {
                $table->dropConstrainedForeignId('player_db_id');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('player_projections', 'player_db_id')) {
            Schema::table('player_projections', function (Blueprint $table) {
                $table->foreignId('player_db_id')
                    ->nullable()
                    ->constrained('players')
                    ->cascadeOnDelete();
            });
        }
    }
};
