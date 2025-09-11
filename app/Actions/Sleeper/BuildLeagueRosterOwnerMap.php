<?php

namespace App\Actions\Sleeper;

class BuildLeagueRosterOwnerMap
{
    public function __construct(
        public FetchRosters $fetchRosters,
        public FetchLeagueOwners $fetchLeagueOwners,
    ) {}

    public function execute(string $leagueId): array
    {
        $rosters = $this->fetchRosters->execute($leagueId);
        $owners = $this->fetchLeagueOwners->execute($leagueId);

        $owners = collect($owners)->keyBy('user_id')->map(function ($owner) {
            return $owner['display_name'] ?? $owner['username'] ?? 'Unknown Owner';
        })->all();

        $map = [];

        foreach ($rosters as $roster) {
            $rosterId = $roster['roster_id'] ?? null;
            $ownerId = $roster['owner_id'] ?? null;
            $ownerName = $ownerId ? ($owners[$ownerId] ?? 'Unknown Owner') : 'Unknown Owner';

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
    }
}
