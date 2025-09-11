<?php

namespace App\Actions\Sleeper;

class BuildPlayerLeagueTeamMap
{
    public function __construct(
        public FetchRosters $fetchRosters,
        public FetchLeagueOwners $fetchLeagueOwners,
    ) {}

    /**
     * Build a mapping of player_id => league team name for a given Sleeper league.
     * Team name uses user metadata team_name if available, else display_name/username/Team {ownerId}.
     *
     * @return array<string, string>
     */
    public function execute(string $leagueId): array
    {
        try {
            $rosters = $this->fetchRosters->execute($leagueId);
            $owners = $this->fetchLeagueOwners->execute($leagueId);

            $ownerById = [];
            foreach ($owners as $owner) {
                $uid = $owner['user_id'] ?? null;
                if ($uid !== null) {
                    $ownerById[$uid] = $owner;
                }
            }

            $ownerIdByRosterId = [];
            foreach ($rosters as $roster) {
                $rid = $roster['roster_id'] ?? null;
                if ($rid !== null) {
                    $ownerIdByRosterId[$rid] = $roster['owner_id'] ?? null;
                }
            }

            $playerIdToTeam = [];
            foreach ($rosters as $roster) {
                $rid = $roster['roster_id'] ?? null;
                $ownerId = $rid !== null ? ($ownerIdByRosterId[$rid] ?? null) : null;
                $owner = $ownerId !== null ? ($ownerById[$ownerId] ?? null) : null;
                $teamName = $owner['metadata']['team_name']
                    ?? ($owner['display_name'] ?? ($owner['username'] ?? ($ownerId ? 'Team '.$ownerId : null)));

                $playerIds = array_unique(array_values(array_merge(
                    (array) ($roster['players'] ?? []),
                    (array) ($roster['starters'] ?? [])
                )));

                foreach ($playerIds as $pid) {
                    if (is_string($pid) || is_int($pid)) {
                        $playerIdToTeam[(string) $pid] = $teamName ?? 'Team';
                    }
                }
            }

            return $playerIdToTeam;
        } catch (\Throwable $e) {
            return [];
        }
    }
}
