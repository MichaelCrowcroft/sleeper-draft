<?php

use App\Console\Commands\ImportSleeperPlayers;
use App\Console\Commands\UpdatePlayerADP;
use App\Console\Commands\UpdatePlayerTrending;
use Illuminate\Support\Facades\Schedule;

Schedule::command(ImportSleeperPlayers::class)->dailyat('00:00');

Schedule::command(UpdatePlayerTrending::class, [
    '--lookback' => 24,
    '--type' => 'add',
    '--limit' => 100,
])->hourly();

Schedule::command(UpdatePlayerTrending::class, [
    '--lookback' => 24,
    '--type' => 'drop',
    '--limit' => 100,
])->hourly();

Schedule::command(UpdatePlayerADP::class, [
    '--teams' => 12,
    '--year' => 2025,
])->dailyAt('02:00');
