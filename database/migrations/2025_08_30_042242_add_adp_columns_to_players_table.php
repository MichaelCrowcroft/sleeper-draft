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
        Schema::table('players', function (Blueprint $table) {
            $table->string('adp_formatted')->nullable();
            $table->integer('times_drafted')->nullable();
            $table->float('adp_high')->nullable();
            $table->float('adp_low')->nullable();
            $table->float('adp_stdev')->nullable();
            $table->integer('bye_week')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn([
                'adp_formatted',
                'times_drafted',
                'adp_high',
                'adp_low',
                'adp_stdev',
                'bye_week',
            ]);
        });
    }
};
