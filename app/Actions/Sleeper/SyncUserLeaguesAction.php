<?php

namespace App\Actions\Sleeper;

use App\Models\League;
use App\Models\Roster;
use App\Models\User;
use App\Services\SleeperSdk;
use Illuminate\Support\Facades\DB;

class SyncUserLeaguesAction
{
    public function __construct(private readonly SleeperSdk $sleeper) {}

    public function execute(User $user, ?string $season = null, bool $force = false): array
    {
        if (empty($user->sleeper_user_id)) {
            throw new \InvalidArgumentException("User {$user->name} does not have a Sleeper account connected.");
        }

        // Get current season if not specified
        if (! $season) {
            $state = $this->sleeper->getState('nfl');
            $season = (string) ($state['season'] ?? date('Y'));
        }

        $leagues = $this->sleeper->getUserLeagues($user->sleeper_user_id, 'nfl', $season);

        if (empty($leagues)) {
            return ['leagues' => 0, 'rosters' => 0];
        }

        $syncedLeagues = 0;
        $syncedRosters = 0;

        DB::transaction(function () use ($leagues, $user, $season, &$syncedLeagues, &$syncedRosters) {
            foreach ($leagues as $remote) {
                $leagueId = (string) ($remote['league_id'] ?? '');
                if (empty($leagueId)) {
                    continue;
                }

                // Use updateOrCreate for proper CRUD update behavior
                $league = League::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'sleeper_league_id' => $leagueId,
                    ],
                    [
                        'name' => (string) ($remote['name'] ?? 'Unknown League'),
                        'season' => (string) ($remote['season'] ?? $season),
                        'sport' => (string) ($remote['sport'] ?? 'nfl'),
                        'avatar' => $remote['avatar'] ?? null,
                        'total_rosters' => isset($remote['total_rosters']) ? (int) $remote['total_rosters'] : null,
                        'settings' => $remote['settings'] ?? null,
                        'metadata' => $remote,
                    ]
                );

                $syncedLeagues++;

                // Fetch rosters for this league
                $rosters = $this->sleeper->getLeagueRosters($leagueId);
                foreach ($rosters as $r) {
                    $ownerId = isset($r['owner_id']) ? (string) $r['owner_id'] : null;
                    if ($ownerId !== $user->sleeper_user_id) {
                        continue; // Only sync user's own roster
                    }

                    $rosterId = (string) ($r['roster_id'] ?? '');
                    if (empty($rosterId)) {
                        continue;
                    }

                    // Use updateOrCreate for proper CRUD update behavior
                    Roster::updateOrCreate(
                        [
                            'league_id' => $league->id,
                            'sleeper_roster_id' => $rosterId,
                        ],
                        [
                            'user_id' => $user->id,
                            'roster_id' => isset($r['roster_id']) ? (int) $r['roster_id'] : null,
                            'owner_id' => $ownerId,
                            'wins' => isset($r['settings']['wins']) ? (int) $r['settings']['wins'] : 0,
                            'losses' => isset($r['settings']['losses']) ? (int) $r['settings']['losses'] : 0,
                            'ties' => isset($r['settings']['ties']) ? (int) $r['settings']['ties'] : 0,
                            'fpts' => isset($r['settings']['fpts']) ? (float) $r['settings']['fpts'] : 0,
                            'fpts_decimal' => isset($r['settings']['fpts_decimal']) ? (float) $r['settings']['fpts_decimal'] : 0,
                            'players' => $r['players'] ?? null,
                            'metadata' => $r,
                        ]
                    );

                    $syncedRosters++;
                }
            }
        });

        return ['leagues' => $syncedLeagues, 'rosters' => $syncedRosters];
    }
}
