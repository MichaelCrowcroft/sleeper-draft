<?php

namespace App\Actions\Rosters;

use App\Http\Resources\PlayerResource;
use App\Models\Player;

class FetchEnhancedRoster
{
    public function execute(string $leagueId, array $roster, bool $includePlayerDetails = true, bool $includeOwnerDetails = true): array
    {
        $enhanced = $roster;

        // Owner details
        if ($includeOwnerDetails && ! empty($roster['owner_id'])) {
            $enhanced['owner'] = $this->buildOwnerDetails($leagueId, (string) $roster['owner_id']);
        }

        // Player details
        if ($includePlayerDetails) {
            $allPlayerIds = array_unique(array_merge(
                (array) ($roster['players'] ?? []),
                (array) ($roster['starters'] ?? []),
                (array) ($roster['bench'] ?? [])
            ));

            $playersFromDb = [];
            if (! empty($allPlayerIds)) {
                $playersFromDb = Player::whereIn('player_id', $allPlayerIds)
                    ->get()
                    ->mapWithKeys(fn ($p) => [$p->player_id => (new PlayerResource($p))->resolve()])
                    ->all();
            }

            $enhanced['players_detailed'] = $this->enhancePlayerArray((array) ($roster['players'] ?? []), $playersFromDb);
            $enhanced['starters_detailed'] = $this->enhancePlayerArray((array) ($roster['starters'] ?? []), $playersFromDb);
            $enhanced['bench_detailed'] = $this->enhancePlayerArray((array) ($roster['bench'] ?? []), $playersFromDb);
        }

        return $enhanced;
    }

    private function enhancePlayerArray(array $playerIds, array $playersFromDb): array
    {
        return array_map(fn ($pid) => [
            'player_id' => $pid,
            'player_data' => $playersFromDb[$pid] ?? null,
        ], $playerIds);
    }

    private function buildOwnerDetails(string $leagueId, string $ownerId): array
    {
        try {
            $users = $this->getLeagueOwners->execute($leagueId);
            if (is_array($users)) {
                foreach ($users as $user) {
                    $userId = $user['user_id'] ?? null;
                    if ((string) $userId === $ownerId) {
                        return [
                            'user_id' => $userId,
                            'username' => $user['username'] ?? null,
                            'display_name' => $user['display_name'] ?? null,
                            'avatar' => $user['avatar'] ?? null,
                            'metadata' => $user['metadata'] ?? [],
                            'team_name' => $user['metadata']['team_name']
                                ?? $user['display_name']
                                ?? $user['username']
                                ?? 'Team '.$ownerId,
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            // Fall through to default
        }

        return [
            'user_id' => $ownerId,
            'username' => null,
            'display_name' => null,
            'avatar' => null,
            'metadata' => [],
            'team_name' => 'Team '.$ownerId,
        ];
    }
}
