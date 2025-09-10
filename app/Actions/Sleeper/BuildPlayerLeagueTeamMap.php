<?php

namespace App\Actions\Sleeper;

class BuildPlayerLeagueTeamMap
{
    public function __construct(
        public FetchRosters $fetchRosters,
        public FetchLeagueUsers $fetchLeagueUsers,
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
            $users = $this->fetchLeagueUsers->execute($leagueId);

            $userById = [];
            foreach ($users as $user) {
                $uid = $user['user_id'] ?? null;
                if ($uid !== null) {
                    $userById[$uid] = $user;
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
                $user = $ownerId !== null ? ($userById[$ownerId] ?? null) : null;
                $teamName = $user['metadata']['team_name']
                    ?? ($user['display_name'] ?? ($user['username'] ?? ($ownerId ? 'Team '.$ownerId : null)));

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
