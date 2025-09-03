<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('player_projections', function (Blueprint $table) {
            // Core fantasy points
            $table->decimal('pts_half_ppr', 8, 2)->nullable()->after('company');
            $table->decimal('pts_ppr', 8, 2)->nullable()->after('pts_half_ppr');
            $table->decimal('pts_std', 8, 2)->nullable()->after('pts_ppr');

            // ADP projections
            $table->unsignedInteger('adp_dd_ppr')->nullable()->after('pts_std');
            $table->unsignedInteger('pos_adp_dd_ppr')->nullable()->after('adp_dd_ppr');

            // Receiving
            $table->decimal('rec', 8, 2)->nullable()->after('pos_adp_dd_ppr');
            $table->decimal('rec_tgt', 8, 2)->nullable()->after('rec');
            $table->decimal('rec_yd', 8, 2)->nullable()->after('rec_tgt');
            $table->decimal('rec_td', 8, 2)->nullable()->after('rec_yd');
            $table->decimal('rec_fd', 8, 2)->nullable()->after('rec_td');

            // Rushing
            $table->decimal('rush_att', 8, 2)->nullable()->after('rec_fd');
            $table->decimal('rush_yd', 8, 2)->nullable()->after('rush_att');

            // Fumbles
            $table->decimal('fum', 8, 2)->nullable()->after('rush_yd');
            $table->decimal('fum_lost', 8, 2)->nullable()->after('fum');

            // Remove JSON stats column now that data is flattened
            if (Schema::hasColumn('player_projections', 'stats')) {
                $table->dropColumn('stats');
            }
        });
    }

    public function down(): void
    {
        Schema::table('player_projections', function (Blueprint $table) {
            $table->json('stats')->nullable()->after('company');

            $table->dropColumn([
                'pts_half_ppr', 'pts_ppr', 'pts_std',
                'adp_dd_ppr', 'pos_adp_dd_ppr',
                'rec', 'rec_tgt', 'rec_yd', 'rec_td', 'rec_fd',
                'rush_att', 'rush_yd',
                'fum', 'fum_lost',
            ]);
        });
    }
};
