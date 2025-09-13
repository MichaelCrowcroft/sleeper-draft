<?php

namespace App\Actions\Matchups;

use Illuminate\Support\Facades\Cache;
use MichaelCrowcroft\SleeperLaravel\Facades\Sleeper;

class GetMatchupsWithOwners
{
    public function __construct(public ?int $ttlSeconds = 300) {}

    public function execute(string $leagueId, int $week): array
    {
        $cacheKey = "sleeper:matchups:{$leagueId}:{$week}";

        return Cache::remember($cacheKey, now()->addSeconds($this->ttlSeconds ?? 300), function () use ($leagueId, $week) {
            $matchups = Sleeper::leagues()->matchups($leagueId, $week)->collect();
            if ($matchups === []) {
                return [];
            }

            $users = Sleeper::leagues()->users($leagueId)->collect();
            $rosters = Sleeper::leagues()->rosters($leagueId)->collect();

            $rosters = $rosters->keyBy(fn ($roster) => (int) $roster['roster_id']);
            $users = $users->keyBy('user_id');

            $matchups = $matchups->map(function ($matchup) use ($rosters, $users) {
                $roster = $rosters->get($matchup['roster_id']);

                if ($roster && isset($roster['owner_id'])) {
                    $user = $users->get($roster['owner_id']);

                    if ($user) {
                        $matchup['owner_name'] = $user['display_name'] ?? $user['username'];
                        $matchup['owner_id'] = $user['user_id'];
                        $matchup['metadata'] = $user['metadata'];
                    }
                }

                if ($roster) {
                    $matchup['roster_settings'] = $roster['settings'] ?? null;
                    $matchup['roster_metadata'] = $roster['metadata'] ?? null;
                }

                return $matchup;
            });

            $groupedMatchups = [];
            foreach ($matchups as $matchup) {
                $matchupId = $matchup['matchup_id'];
                if (! isset($groupedMatchups[$matchupId])) {
                    $groupedMatchups[$matchupId] = [];
                }
                $groupedMatchups[$matchupId][] = $matchup;
            }

            return $groupedMatchups;
        });
    }
}
