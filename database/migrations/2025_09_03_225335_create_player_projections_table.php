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
            $table->string('player_id')->nullable();
            $table->date('game_date')->nullable();

            // Additional game participation stats
            $table->integer('st_snp')->nullable();

            // Passing stats
            $table->decimal('pass_cmp')->nullable();
            $table->decimal('pass_att')->nullable();
            $table->decimal('pass_yd')->nullable();
            $table->decimal('pass_td')->nullable();
            $table->decimal('pass_int')->nullable();
            $table->decimal('pass_sacked')->nullable();
            $table->decimal('pass_sacked_yd')->nullable();
            $table->decimal('pass_rtg')->nullable();
            $table->decimal('cmp_pct')->nullable();
            $table->decimal('pass_ypa')->nullable();
            $table->decimal('pass_ypc')->nullable();
            $table->decimal('pass_lng')->nullable();
            $table->decimal('pass_fd')->nullable();
            $table->decimal('pass_air_yd')->nullable();
            $table->decimal('pass_rush_yd')->nullable();
            $table->decimal('pass_td_lng')->nullable();
            $table->decimal('pass_inc')->nullable();
            $table->decimal('pass_rz_att')->nullable();

            // Additional rushing stats
            $table->decimal('rush_lng')->nullable();
            $table->decimal('rush_ypa')->nullable();
            $table->decimal('rush_fd')->nullable();
            $table->decimal('rush_td_lng')->nullable();
            $table->decimal('rush_rz_att')->nullable();
            $table->decimal('rush_tkl_loss')->nullable();
            $table->decimal('rush_tkl_loss_yd')->nullable();
            $table->decimal('rush_yac')->nullable();

            // Additional receiving stats
            $table->decimal('rec_ypr')->nullable();
            $table->decimal('rec_ypt')->nullable();
            $table->decimal('rec_yar')->nullable();
            $table->decimal('rec_drop')->nullable();

            // Receiving distance breakdowns
            $table->decimal('rec_0_4')->nullable();
            $table->decimal('rec_5_9')->nullable();
            $table->decimal('rec_10_19')->nullable();
            $table->decimal('rec_20_29')->nullable();
            $table->decimal('rec_30_39')->nullable();
            $table->decimal('rec_40p')->nullable();

            // Red zone receiving
            $table->decimal('rec_td_lng')->nullable();
            $table->decimal('rec_td_40p')->nullable();
            $table->decimal('rec_td_50p')->nullable();

            // Combined rushing/receiving yards
            $table->decimal('rush_rec_yd')->nullable();

            // Additional fumbles
            $table->decimal('fum_lost')->nullable();

            // Turnovers
            $table->decimal('to')->nullable();

            // Penalties
            $table->decimal('penalty')->nullable();
            $table->decimal('penalty_yd')->nullable();

            // Defensive stats
            $table->decimal('def_int')->nullable();
            $table->decimal('def_int_yd')->nullable();
            $table->decimal('def_int_td')->nullable();
            $table->decimal('def_sack')->nullable();
            $table->decimal('def_sack_yd')->nullable();
            $table->decimal('def_ff')->nullable();
            $table->decimal('def_fr')->nullable();
            $table->decimal('def_fr_yd')->nullable();
            $table->decimal('def_fr_td')->nullable();
            $table->decimal('def_td')->nullable();
            $table->decimal('def_sfty')->nullable();
            $table->decimal('idp_tkl')->nullable();
            $table->decimal('idp_tkl_solo')->nullable();

            // Special teams
            $table->decimal('st_td')->nullable();
            $table->decimal('st_ff')->nullable();
            $table->decimal('st_fr')->nullable();
            $table->decimal('st_fum_rec')->nullable();

            // Kicking stats
            $table->decimal('xpm')->nullable();
            $table->decimal('xpa')->nullable();
            $table->decimal('fgm')->nullable();
            $table->decimal('fga')->nullable();
            $table->decimal('fgm_0_19')->nullable();
            $table->decimal('fgm_20_29')->nullable();
            $table->decimal('fgm_30_39')->nullable();
            $table->decimal('fgm_40_49')->nullable();
            $table->decimal('fgm_50p')->nullable();
            $table->decimal('fga_0_19')->nullable();
            $table->decimal('fga_20_29')->nullable();
            $table->decimal('fga_30_39')->nullable();
            $table->decimal('fga_40_49')->nullable();
            $table->decimal('fga_50p')->nullable();

            // Punting stats
            $table->decimal('punt')->nullable();
            $table->decimal('punt_yd')->nullable();
            $table->decimal('punt_lng')->nullable();
            $table->decimal('punt_tb')->nullable();
            $table->decimal('punt_in_20')->nullable();

            // Bonus stats
            $table->decimal('bonus_fd_wr')->nullable();
            $table->decimal('bonus_rec_wr')->nullable();
            $table->decimal('bonus_rush_wr')->nullable();
            $table->decimal('bonus_fd_qb')->nullable();
            $table->decimal('bonus_fd_rb')->nullable();
            $table->decimal('bonus_fd_te')->nullable();
            $table->decimal('bonus_rec_yd_100')->nullable();
            $table->decimal('bonus_rush_rec_yd_100')->nullable();
            $table->decimal('bonus_rec_yd_200')->nullable();
            $table->decimal('bonus_rush_rec_yd_200')->nullable();

            // Anytime TDs
            $table->decimal('anytime_tds')->nullable();

            // Additional snap counts
            $table->decimal('def_snp')->nullable();

            // Position rankings
            $table->decimal('pos_rank_half_ppr')->nullable();
            $table->decimal('pos_rank_ppr')->nullable();
            $table->decimal('pos_rank_std')->nullable();

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
        Schema::dropIfExists('player_projections');
    }
};
