<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('player_stats', function (Blueprint $table) {
            // Core fantasy points
            $table->decimal('pts_half_ppr', 8, 2)->nullable()->after('company');
            $table->decimal('pts_ppr', 8, 2)->nullable()->after('pts_half_ppr');
            $table->decimal('pts_std', 8, 2)->nullable()->after('pts_ppr');

            // Ranks
            $table->unsignedInteger('pos_rank_half_ppr')->nullable()->after('pts_std');
            $table->unsignedInteger('pos_rank_ppr')->nullable()->after('pos_rank_half_ppr');
            $table->unsignedInteger('pos_rank_std')->nullable()->after('pos_rank_ppr');

            // Games / snaps
            $table->unsignedInteger('gp')->nullable()->after('pos_rank_std');
            $table->unsignedInteger('gs')->nullable()->after('gp');
            $table->unsignedInteger('gms_active')->nullable()->after('gs');
            $table->unsignedInteger('off_snp')->nullable()->after('gms_active');
            $table->unsignedInteger('tm_off_snp')->nullable()->after('off_snp');
            $table->unsignedInteger('tm_def_snp')->nullable()->after('tm_off_snp');
            $table->unsignedInteger('tm_st_snp')->nullable()->after('tm_def_snp');

            // Receiving
            $table->decimal('rec', 8, 2)->nullable()->after('tm_st_snp');
            $table->decimal('rec_tgt', 8, 2)->nullable()->after('rec');
            $table->decimal('rec_yd', 8, 2)->nullable()->after('rec_tgt');
            $table->decimal('rec_td', 8, 2)->nullable()->after('rec_yd');
            $table->decimal('rec_fd', 8, 2)->nullable()->after('rec_td');
            $table->decimal('rec_air_yd', 10, 2)->nullable()->after('rec_fd');
            $table->decimal('rec_rz_tgt', 8, 2)->nullable()->after('rec_air_yd');
            $table->unsignedInteger('rec_lng')->nullable()->after('rec_rz_tgt');

            // Rushing
            $table->decimal('rush_att', 8, 2)->nullable()->after('rec_lng');
            $table->decimal('rush_yd', 8, 2)->nullable()->after('rush_att');
            $table->decimal('rush_td', 8, 2)->nullable()->after('rush_yd');

            // Fumbles
            $table->decimal('fum', 8, 2)->nullable()->after('rush_td');
            $table->decimal('fum_lost', 8, 2)->nullable()->after('fum');

            // Remove JSON stats column now that data is flattened
            if (Schema::hasColumn('player_stats', 'stats')) {
                $table->dropColumn('stats');
            }
        });
    }

    public function down(): void
    {
        Schema::table('player_stats', function (Blueprint $table) {
            // Recreate stats JSON
            $table->json('stats')->nullable()->after('company');

            // Drop added columns
            $table->dropColumn([
                'pts_half_ppr', 'pts_ppr', 'pts_std',
                'pos_rank_half_ppr', 'pos_rank_ppr', 'pos_rank_std',
                'gp', 'gs', 'gms_active', 'off_snp', 'tm_off_snp', 'tm_def_snp', 'tm_st_snp',
                'rec', 'rec_tgt', 'rec_yd', 'rec_td', 'rec_fd', 'rec_air_yd', 'rec_rz_tgt', 'rec_lng',
                'rush_att', 'rush_yd', 'rush_td',
                'fum', 'fum_lost',
            ]);
        });
    }
};
