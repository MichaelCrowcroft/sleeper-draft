<?php

namespace App\Actions\Sleeper;

use App\Models\League;
use App\Models\Roster;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ClearUserDataAction
{
    public function execute(User $user): void
    {
        DB::transaction(function () use ($user) {
            // Delete rosters first due to foreign key constraints
            Roster::where('user_id', $user->id)->delete();
            // Then delete leagues
            League::where('user_id', $user->id)->delete();
        });
    }
}
