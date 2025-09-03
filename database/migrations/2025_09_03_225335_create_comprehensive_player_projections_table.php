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
            // Foreign key and identifiers
            $table->foreignId('player_id')->constrained()->onDelete('cascade');
            $table->string('sleeper_player_id');
            $table->integer('season');
            $table->integer('week');
            $table->string('season_type')->default('regular');
            $table->string('source')->nullable(); // e.g., 'espn', 'yahoo', 'sleeper'

            // Game participation stats
            $table->decimal('gms_active', 3, 1)->nullable();
            $table->decimal('gp', 3, 1)->nullable();
            $table->decimal('gs', 3, 1)->nullable();

            // Fantasy points
            $table->decimal('pts_half_ppr', 6, 2)->nullable();
            $table->decimal('pts_ppr', 6, 2)->nullable();
            $table->decimal('pts_std', 6, 2)->nullable();

            // Passing stats
            $table->decimal('pass_cmp', 4, 1)->nullable();
            $table->decimal('pass_att', 4, 1)->nullable();
            $table->decimal('pass_yd', 5, 1)->nullable();
            $table->decimal('pass_td', 3, 1)->nullable();
            $table->decimal('pass_int', 3, 1)->nullable();
            $table->decimal('pass_sacked', 3, 1)->nullable();
            $table->decimal('pass_sacked_yd', 4, 1)->nullable();
            $table->decimal('pass_rtg', 5, 1)->nullable();
            $table->decimal('cmp_pct', 5, 2)->nullable();
            $table->decimal('pass_ypa', 5, 2)->nullable();
            $table->decimal('pass_ypc', 5, 2)->nullable();
            $table->decimal('pass_lng', 4, 1)->nullable();
            $table->decimal('pass_fd', 3, 1)->nullable();

            // Rushing stats
            $table->decimal('rush_att', 4, 1)->nullable();
            $table->decimal('rush_yd', 5, 1)->nullable();
            $table->decimal('rush_td', 3, 1)->nullable();
            $table->decimal('rush_lng', 4, 1)->nullable();
            $table->decimal('rush_ypa', 5, 2)->nullable();
            $table->decimal('rush_fd', 3, 1)->nullable();

            // Receiving stats
            $table->decimal('rec', 4, 1)->nullable();
            $table->decimal('rec_yd', 5, 1)->nullable();
            $table->decimal('rec_td', 3, 1)->nullable();
            $table->decimal('rec_lng', 4, 1)->nullable();
            $table->decimal('rec_tgt', 4, 1)->nullable();
            $table->decimal('rec_ypr', 5, 2)->nullable();
            $table->decimal('rec_ypt', 5, 2)->nullable();
            $table->decimal('rec_fd', 3, 1)->nullable();
            $table->decimal('rec_air_yd', 5, 1)->nullable();

            // Receiving distance breakdowns
            $table->decimal('rec_0_4', 4, 1)->nullable();
            $table->decimal('rec_5_9', 4, 1)->nullable();
            $table->decimal('rec_10_19', 4, 1)->nullable();
            $table->decimal('rec_20_29', 4, 1)->nullable();
            $table->decimal('rec_30_39', 4, 1)->nullable();
            $table->decimal('rec_40p', 4, 1)->nullable();

            // Red zone receiving
            $table->decimal('rec_rz_tgt', 4, 1)->nullable();
            $table->decimal('rec_td_lng', 4, 1)->nullable();

            // Combined rushing/receiving yards
            $table->decimal('rush_rec_yd', 5, 1)->nullable();

            // Fumbles
            $table->decimal('fum', 3, 1)->nullable();
            $table->decimal('fum_lost', 3, 1)->nullable();

            // Turnovers
            $table->decimal('to', 3, 1)->nullable();

            // Penalties
            $table->decimal('penalty', 3, 1)->nullable();
            $table->decimal('penalty_yd', 4, 1)->nullable();

            // Defensive stats
            $table->decimal('def_int', 3, 1)->nullable();
            $table->decimal('def_int_yd', 4, 1)->nullable();
            $table->decimal('def_int_td', 3, 1)->nullable();
            $table->decimal('def_sack', 3, 1)->nullable();
            $table->decimal('def_sack_yd', 4, 1)->nullable();
            $table->decimal('def_ff', 3, 1)->nullable();
            $table->decimal('def_fr', 3, 1)->nullable();
            $table->decimal('def_fr_yd', 4, 1)->nullable();
            $table->decimal('def_fr_td', 3, 1)->nullable();
            $table->decimal('def_td', 3, 1)->nullable();
            $table->decimal('def_sfty', 3, 1)->nullable();

            // Special teams
            $table->decimal('st_td', 3, 1)->nullable();
            $table->decimal('st_ff', 3, 1)->nullable();
            $table->decimal('st_fr', 3, 1)->nullable();
            $table->decimal('st_fum_rec', 3, 1)->nullable();

            // Kicking stats
            $table->decimal('xpm', 3, 1)->nullable();
            $table->decimal('xpa', 3, 1)->nullable();
            $table->decimal('fgm', 3, 1)->nullable();
            $table->decimal('fga', 3, 1)->nullable();
            $table->decimal('fgm_0_19', 3, 1)->nullable();
            $table->decimal('fgm_20_29', 3, 1)->nullable();
            $table->decimal('fgm_30_39', 3, 1)->nullable();
            $table->decimal('fgm_40_49', 3, 1)->nullable();
            $table->decimal('fgm_50p', 3, 1)->nullable();
            $table->decimal('fga_0_19', 3, 1)->nullable();
            $table->decimal('fga_20_29', 3, 1)->nullable();
            $table->decimal('fga_30_39', 3, 1)->nullable();
            $table->decimal('fga_40_49', 3, 1)->nullable();
            $table->decimal('fga_50p', 3, 1)->nullable();

            // Punting stats
            $table->decimal('punt', 3, 1)->nullable();
            $table->decimal('punt_yd', 4, 1)->nullable();
            $table->decimal('punt_lng', 4, 1)->nullable();
            $table->decimal('punt_tb', 3, 1)->nullable();
            $table->decimal('punt_in_20', 3, 1)->nullable();

            // Bonus stats
            $table->decimal('bonus_fd_wr', 3, 1)->nullable();
            $table->decimal('bonus_rec_wr', 3, 1)->nullable();
            $table->decimal('bonus_rush_wr', 3, 1)->nullable();
            $table->decimal('bonus_fd_qb', 3, 1)->nullable();
            $table->decimal('bonus_fd_rb', 3, 1)->nullable();
            $table->decimal('bonus_fd_te', 3, 1)->nullable();
            $table->decimal('bonus_rec_yd_100', 3, 1)->nullable();
            $table->decimal('bonus_rush_rec_yd_100', 3, 1)->nullable();
            $table->decimal('bonus_rec_yd_200', 3, 1)->nullable();
            $table->decimal('bonus_rush_rec_yd_200', 3, 1)->nullable();

            // Anytime TDs
            $table->decimal('anytime_tds', 3, 1)->nullable();

            // Snap counts
            $table->decimal('off_snp', 4, 1)->nullable();
            $table->decimal('def_snp', 4, 1)->nullable();
            $table->decimal('st_snp', 4, 1)->nullable();
            $table->decimal('tm_off_snp', 4, 1)->nullable();
            $table->decimal('tm_def_snp', 4, 1)->nullable();
            $table->decimal('tm_st_snp', 4, 1)->nullable();

            // Position rankings
            $table->decimal('pos_rank_half_ppr', 4, 1)->nullable();
            $table->decimal('pos_rank_ppr', 4, 1)->nullable();
            $table->decimal('pos_rank_std', 4, 1)->nullable();

            // Indexes for performance
            $table->index(['sleeper_player_id', 'season', 'week']);
            $table->index(['season', 'week']);
            $table->index(['source']);

            // Unique constraint to prevent duplicates
            $table->unique(['sleeper_player_id', 'season', 'week', 'season_type', 'source'], 'unique_player_week_projections');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('player_projections', function (Blueprint $table) {
            $table->dropUnique('unique_player_week_projections');
            $table->dropIndex(['sleeper_player_id', 'season', 'week']);
            $table->dropIndex(['season', 'week']);
            $table->dropIndex(['source']);
            $table->dropForeign(['player_id']);
            $table->dropColumn([
                'player_id', 'sleeper_player_id', 'season', 'week', 'season_type', 'source',
                'gms_active', 'gp', 'gs', 'pts_half_ppr', 'pts_ppr', 'pts_std',
                'pass_cmp', 'pass_att', 'pass_yd', 'pass_td', 'pass_int', 'pass_sacked',
                'pass_sacked_yd', 'pass_rtg', 'cmp_pct', 'pass_ypa', 'pass_ypc', 'pass_lng', 'pass_fd',
                'rush_att', 'rush_yd', 'rush_td', 'rush_lng', 'rush_ypa', 'rush_fd',
                'rec', 'rec_yd', 'rec_td', 'rec_lng', 'rec_tgt', 'rec_ypr', 'rec_ypt', 'rec_fd', 'rec_air_yd',
                'rec_0_4', 'rec_5_9', 'rec_10_19', 'rec_20_29', 'rec_30_39', 'rec_40p',
                'rec_rz_tgt', 'rec_td_lng', 'rush_rec_yd', 'fum', 'fum_lost', 'to',
                'penalty', 'penalty_yd', 'def_int', 'def_int_yd', 'def_int_td', 'def_sack',
                'def_sack_yd', 'def_ff', 'def_fr', 'def_fr_yd', 'def_fr_td', 'def_td', 'def_sfty',
                'st_td', 'st_ff', 'st_fr', 'st_fum_rec', 'xpm', 'xpa', 'fgm', 'fga',
                'fgm_0_19', 'fgm_20_29', 'fgm_30_39', 'fgm_40_49', 'fgm_50p',
                'fga_0_19', 'fga_20_29', 'fga_30_39', 'fga_40_49', 'fga_50p',
                'punt', 'punt_yd', 'punt_lng', 'punt_tb', 'punt_in_20',
                'bonus_fd_wr', 'bonus_rec_wr', 'bonus_rush_wr', 'bonus_fd_qb', 'bonus_fd_rb', 'bonus_fd_te',
                'bonus_rec_yd_100', 'bonus_rush_rec_yd_100', 'bonus_rec_yd_200', 'bonus_rush_rec_yd_200',
                'anytime_tds', 'off_snp', 'def_snp', 'st_snp', 'tm_off_snp', 'tm_def_snp', 'tm_st_snp',
                'pos_rank_half_ppr', 'pos_rank_ppr', 'pos_rank_std',
            ]);
        });
    }
};
