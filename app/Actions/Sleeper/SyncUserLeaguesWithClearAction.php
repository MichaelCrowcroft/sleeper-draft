<?php

namespace App\Actions\Sleeper;

use App\Models\User;

class SyncUserLeaguesWithClearAction
{
    public function __construct(
        private readonly ClearUserDataAction $clearUserDataAction,
        private readonly SyncUserLeaguesAction $syncUserLeaguesAction
    ) {}

    public function execute(User $user, ?string $season = null): array
    {
        // Clear existing data first to ensure clean state
        $this->clearUserDataAction->execute($user);

        // Then sync fresh data
        return $this->syncUserLeaguesAction->execute($user, $season, true);
    }
}
