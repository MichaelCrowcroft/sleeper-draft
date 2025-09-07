<?php

namespace App\Actions\Matchups;

use App\Actions\Sleeper\FetchLeague;
use App\Actions\Sleeper\FetchLeagueUsers;
use App\Actions\Sleeper\FetchMatchups;
use App\Actions\Sleeper\FetchRosters;
use App\Models\Player;
use Illuminate\Support\Facades\Auth;

class AssembleMatchupViewModel
{
    public function __construct(
        public DetermineCurrentWeek $determineCurrentWeek,
        public FetchLeague $fetchLeague,
        public FetchRosters $fetchRosters,
        public FetchMatchups $fetchMatchups,
        public FetchLeagueUsers $fetchLeagueUsers,
        public BuildLineupsFromRosters $buildLineupsFromRosters,
        public ComputePlayerWeekPoints $computePlayerWeekPoints,
        public AggregateTeamTotals $aggregateTeamTotals,
    ) {}

    /**
     * Build a normalized matchup VM for a given league & week for the specified rosterId.
     */
    public function execute(string $leagueId, ?int $week, int $rosterId): array
    {
        $state = $this->determineCurrentWeek->execute('nfl');
        $resolvedWeek = $week ?? $state['week'];
        $season = $state['season'];

        $league = $this->fetchLeague->execute($leagueId);
        $rosters = $this->fetchRosters->execute($leagueId);
        $users = $this->fetchLeagueUsers->execute($leagueId);
        $matchups = $this->fetchMatchups->execute($leagueId, $resolvedWeek);

        // Index rosters by roster_id
        $rosterById = [];
        foreach ($rosters as $r) {
            $rosterById[(int) ($r['roster_id'] ?? 0)] = $r;
        }

        // Index users by user_id
        $userById = [];
        foreach ($users as $u) {
            $userById[(string) ($u['user_id'] ?? '')] = $u;
        }

        // Determine selected roster id robustly
        $allRosterIds = array_map(fn ($r) => (int) ($r['roster_id'] ?? 0), $rosters);
        $selectedRosterId = in_array($rosterId, $allRosterIds, true) ? $rosterId : 0;

        if ($selectedRosterId === 0) {
            $auth = Auth::user();
            $sleeperUserId = $auth->sleeper_user_id ?? null;
            if ($sleeperUserId) {
                foreach ($rosters as $r) {
                    if (($r['owner_id'] ?? null) == $sleeperUserId) {
                        $selectedRosterId = (int) ($r['roster_id'] ?? 0);
                        break;
                    }
                }
            }
        }

        if ($selectedRosterId === 0) {
            // fallback to first available in matchups or rosters
            if (! empty($matchups)) {
                $selectedRosterId = (int) ($matchups[0]['roster_id'] ?? 0);
            } elseif (! empty($rosters)) {
                $selectedRosterId = (int) ($rosters[0]['roster_id'] ?? 0);
            }
        }

        // Find this roster's matchup_id and opponent roster
        $myEntries = array_values(array_filter($matchups, fn ($m) => (int) ($m['roster_id'] ?? 0) === $selectedRosterId));
        $myMatchupId = isset($myEntries[0]['matchup_id']) ? (int) $myEntries[0]['matchup_id'] : null;
        $pair = $myMatchupId !== null
            ? array_values(array_filter($matchups, fn ($m) => isset($m['matchup_id']) && (int) $m['matchup_id'] === $myMatchupId))
            : [];

        // Fallback: find first complete pair
        if (count($pair) < 2) {
            $byMatchup = [];
            foreach ($matchups as $m) {
                if (! isset($m['matchup_id'])) {
                    continue;
                }
                $byMatchup[$m['matchup_id']][] = $m;
            }
            foreach ($byMatchup as $mid => $entries) {
                if (count($entries) >= 2) {
                    $pair = array_values($entries);
                    $myMatchupId = (int) $mid;
                    break;
                }
            }
        }

        if (count($pair) < 2) {
            return [
                'league' => $league,
                'week' => $resolvedWeek,
                'season' => $season,
                'error' => 'Matchup not found for this week',
            ];
        }

        $home = $pair[0];
        $away = $pair[1];
        if ((int) $home['roster_id'] !== $selectedRosterId) {
            [$home, $away] = [$away, $home];
        }

        $lineups = $this->buildLineupsFromRosters->execute($rosters);
        $homeLineup = $lineups[(int) $home['roster_id']] ?? ['starters' => [], 'bench' => []];
        $awayLineup = $lineups[(int) $away['roster_id']] ?? ['starters' => [], 'bench' => []];

        $homePoints = $this->computePlayerWeekPoints->execute($homeLineup['starters'], $season, $resolvedWeek);
        $awayPoints = $this->computePlayerWeekPoints->execute($awayLineup['starters'], $season, $resolvedWeek);

        $homeTotals = $this->aggregateTeamTotals->execute($homePoints);
        $awayTotals = $this->aggregateTeamTotals->execute($awayPoints);

        // Simple variance: assume 6.0 per starter not yet locked
        $estimateVariance = function (array $points): float {
            $variance = 0.0;
            foreach ($points as $row) {
                $variance += $row['status'] === 'locked' ? 1.0 : 36.0; // stddev 1 for completed, 6 for remaining
            }

            return $variance;
        };

        $homeModel = [
            'mean' => $homeTotals['total_estimated'],
            'variance' => $estimateVariance($homePoints),
        ];
        $awayModel = [
            'mean' => $awayTotals['total_estimated'],
            'variance' => $estimateVariance($awayPoints),
        ];

        $prob = app(ComputeWinProbability::class)->execute($homeModel, $awayModel);

        $ownerName = function (array $r) use ($rosterById, $userById): ?string {
            $ownerId = $rosterById[(int) ($r['roster_id'] ?? 0)]['owner_id'] ?? null;
            if ($ownerId && isset($userById[(string) $ownerId])) {
                $u = $userById[(string) $ownerId];

                return $u['display_name'] ?? ($u['username'] ?? null);
            }

            return null;
        };

        $rosterOptions = [];
        foreach ($rosters as $r) {
            $rid = (int) ($r['roster_id'] ?? 0);
            $oid = $r['owner_id'] ?? null;
            $label = isset($userById[(string) $oid])
                ? ($userById[(string) $oid]['display_name'] ?? $userById[(string) $oid]['username'] ?? ('Roster '.$rid))
                : ('Roster '.$rid);
            $rosterOptions[] = ['value' => $rid, 'label' => $label];
        }

        // Fetch player names for all players in the matchup
        $allPlayerIds = array_unique(array_merge($homeLineup['starters'], $awayLineup['starters']));
        $playerLookup = [];

        if (! empty($allPlayerIds)) {
            $players = Player::whereIn('player_id', $allPlayerIds)
                ->select(['player_id', 'full_name', 'first_name', 'last_name', 'team', 'position'])
                ->get()
                ->keyBy('player_id');

            foreach ($allPlayerIds as $playerId) {
                $player = $players->get($playerId);
                if ($player) {
                    $displayName = $player->full_name ?: ($player->first_name.' '.$player->last_name);
                    $playerLookup[$playerId] = [
                        'name' => trim($displayName) ?: $playerId,
                        'team' => $player->team,
                        'position' => $player->position,
                    ];
                } else {
                    // Fallback if player not found in database
                    $playerLookup[$playerId] = [
                        'name' => $playerId,
                        'team' => null,
                        'position' => null,
                    ];
                }
            }
        }

        return [
            'league' => [
                'id' => $leagueId,
                'name' => $league['name'] ?? 'League',
            ],
            'week' => $resolvedWeek,
            'season' => $season,
            'home' => [
                'roster_id' => (int) $home['roster_id'],
                'owner_id' => $rosterById[(int) $home['roster_id']]['owner_id'] ?? null,
                'owner_name' => $ownerName($home),
                'starters' => $homeLineup['starters'],
                'points' => $homePoints,
                'totals' => $homeTotals,
            ],
            'away' => [
                'roster_id' => (int) $away['roster_id'],
                'owner_id' => $rosterById[(int) $away['roster_id']]['owner_id'] ?? null,
                'owner_name' => $ownerName($away),
                'starters' => $awayLineup['starters'],
                'points' => $awayPoints,
                'totals' => $awayTotals,
            ],
            'win_probability' => $prob,
            'roster_options' => $rosterOptions,
            'selected_roster_id' => $selectedRosterId,
            'players' => $playerLookup,
        ];
    }
}
