<?php

namespace App\Actions\Sleeper;

use Illuminate\Support\Facades\Log;

class BuildLeagueRosterOwnerMap
{
    public function __construct(
        public FetchRosters $fetchRosters,
        public FetchLeagueUsers $fetchLeagueUsers,
    ) {}

    /**
     * Build a mapping of player_id => owner metadata for a given league.
     *
     * Returns an associative array keyed by player_id with values:
     * [ 'owner' => display_name|username|"Unknown Owner", 'roster_id' => int, 'owner_id' => string|null ]
     */
    public function execute(string $leagueId): array
    {
        try {
            $rosters = $this->fetchRosters->execute($leagueId);
            $users = $this->fetchLeagueUsers->execute($leagueId);

            // Create user map: user_id => display name
            $userMap = collect($users)->keyBy('user_id')->map(function ($user) {
                return $user['display_name']
                    ?? $user['username']
                    ?? 'Unknown Owner';
            });

            $map = [];

            foreach ($rosters as $roster) {
                $rosterId = $roster['roster_id'] ?? null;
                $ownerId = $roster['owner_id'] ?? null;
                $ownerName = $ownerId ? ($userMap[$ownerId] ?? 'Unknown Owner') : 'Unknown Owner';

                $players = is_array($roster['players'] ?? null) ? $roster['players'] : [];

                foreach ($players as $playerId) {
                    if ($playerId === null) {
                        continue;
                    }

                    $map[(string) $playerId] = [
                        'owner' => $ownerName,
                        'roster_id' => $rosterId,
                        'owner_id' => $ownerId,
                    ];
                }
            }

            return $map;
        } catch (\Throwable $e) {
            Log::error('BuildLeagueRosterOwnerMap failed', [
                'league_id' => $leagueId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
