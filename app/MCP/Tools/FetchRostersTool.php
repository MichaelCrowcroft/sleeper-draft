<?php

namespace App\MCP\Tools;

use App\Http\Resources\PlayerResource;
use App\Models\Player;
use Illuminate\Support\Facades\Validator;
use MichaelCrowcroft\SleeperLaravel\Facades\Sleeper;
use OPGG\LaravelMcpServer\Exceptions\Enums\JsonRpcErrorCode;
use OPGG\LaravelMcpServer\Exceptions\JsonRpcErrorException;
use OPGG\LaravelMcpServer\Services\ToolService\ToolInterface;

class FetchRostersTool implements ToolInterface
{
    public function isStreaming(): bool
    {
        return false;
    }

    public function name(): string
    {
        return 'fetch-rosters';
    }

    public function description(): string
    {
        return 'Fetches rosters for a league using the Sleeper SDK. Returns roster data with player information from the database and owner details when available.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'league_id' => [
                    'type' => 'string',
                    'description' => 'Sleeper league ID to fetch rosters for',
                ],
                'include_player_details' => [
                    'type' => 'boolean',
                    'description' => 'Whether to include detailed player information from database (default: true)',
                    'default' => true,
                ],
                'include_owner_details' => [
                    'type' => 'boolean',
                    'description' => 'Whether to include owner/user details from Sleeper API (default: true)',
                    'default' => true,
                ],
            ],
            'required' => ['league_id'],
        ];
    }

    public function annotations(): array
    {
        return [
            'title' => 'Fetch League Rosters',
            'readOnlyHint' => true,
            'destructiveHint' => false,
            'idempotentHint' => true,
            'openWorldHint' => true, // Makes API calls to Sleeper

            // Custom annotations
            'category' => 'fantasy-sports',
            'data_source' => 'external_api_and_database',
            'cache_recommended' => true,
        ];
    }

    public function execute(array $arguments): mixed
    {
        // Validate input arguments
        $validator = Validator::make($arguments, [
            'league_id' => ['required', 'string'],
            'include_player_details' => ['nullable', 'boolean'],
            'include_owner_details' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            throw new JsonRpcErrorException(
                message: 'Validation failed: '.$validator->errors()->first(),
                code: JsonRpcErrorCode::INVALID_REQUEST
            );
        }

        $leagueId = $arguments['league_id'];
        $includePlayerDetails = $arguments['include_player_details'] ?? true;
        $includeOwnerDetails = $arguments['include_owner_details'] ?? true;

        // Fetch rosters from Sleeper API
        $rosters = $this->fetchRosters($leagueId);

        // Enhance rosters with player and owner details
        $enhancedRosters = $this->enhanceRosters($leagueId, $rosters, $includePlayerDetails, $includeOwnerDetails);

        return [
            'success' => true,
            'data' => $enhancedRosters,
            'count' => count($enhancedRosters),
            'metadata' => [
                'league_id' => $leagueId,
                'include_player_details' => $includePlayerDetails,
                'include_owner_details' => $includeOwnerDetails,
                'executed_at' => now()->toISOString(),
            ],
        ];
    }

    private function fetchRosters(string $leagueId): array
    {
        $response = Sleeper::leagues()->rosters($leagueId);

        if (! $response->successful()) {
            throw new JsonRpcErrorException(
                message: 'Failed to fetch rosters from Sleeper API',
                code: JsonRpcErrorCode::INTERNAL_ERROR
            );
        }

        $rosters = $response->json();

        if (! is_array($rosters)) {
            throw new JsonRpcErrorException(
                message: 'Invalid rosters data received from API',
                code: JsonRpcErrorCode::INTERNAL_ERROR
            );
        }

        return $rosters;
    }

    private function enhanceRosters(string $leagueId, array $rosters, bool $includePlayerDetails, bool $includeOwnerDetails): array
    {
        $enhancedRosters = [];

        // Get all unique player IDs for batch database lookup
        $allPlayerIds = [];
        if ($includePlayerDetails) {
            foreach ($rosters as $roster) {
                $players = array_merge(
                    $roster['players'] ?? [],
                    $roster['starters'] ?? [],
                    $roster['bench'] ?? []
                );
                $allPlayerIds = array_merge($allPlayerIds, $players);
            }
            $allPlayerIds = array_unique($allPlayerIds);
        }

        // Fetch player data from database in a single query and prepare resources
        $playersFromDb = [];
        if (! empty($allPlayerIds) && $includePlayerDetails) {
            $playersFromDb = Player::whereIn('player_id', $allPlayerIds)
                ->get()
                ->mapWithKeys(fn ($player) => [
                    $player->player_id => (new PlayerResource($player))->resolve(),
                ])
                ->all();
        }

        // Get all unique owner IDs for batch user lookup
        $ownerIds = [];
        if ($includeOwnerDetails) {
            foreach ($rosters as $roster) {
                if (! empty($roster['owner_id'])) {
                    $ownerIds[] = $roster['owner_id'];
                }
            }
            $ownerIds = array_unique($ownerIds);
        }

        // Fetch owner details from Sleeper API
        $ownerDetails = [];
        if (! empty($ownerIds) && $includeOwnerDetails) {
            $ownerDetails = $this->fetchOwnerDetails($leagueId, $ownerIds);
        }

        // Process each roster
        foreach ($rosters as $roster) {
            $enhancedRoster = $roster;

            // Add owner details if available
            if ($includeOwnerDetails && ! empty($roster['owner_id'])) {
                $enhancedRoster['owner'] = $ownerDetails[$roster['owner_id']] ?? null;
            }

            // Enhance player arrays with database information
            if ($includePlayerDetails) {
                $enhancedRoster['players_detailed'] = $this->enhancePlayerArray($roster['players'] ?? [], $playersFromDb);
                $enhancedRoster['starters_detailed'] = $this->enhancePlayerArray($roster['starters'] ?? [], $playersFromDb);
                $enhancedRoster['bench_detailed'] = $this->enhancePlayerArray($roster['bench'] ?? [], $playersFromDb);
            }

            $enhancedRosters[] = $enhancedRoster;
        }

        return $enhancedRosters;
    }

    private function enhancePlayerArray(array $playerIds, array $playersFromDb): array
    {
        return array_map(fn ($playerId) => [
            'player_id' => $playerId,
            'player_data' => $playersFromDb[$playerId] ?? null,
        ], $playerIds);
    }

    private function fetchOwnerDetails(string $leagueId, array $ownerIds): array
    {
        $ownerDetails = [];

        try {
            $response = Sleeper::leagues()->users($leagueId);

            if ($response->successful()) {
                $users = $response->json();

                if (is_array($users)) {
                    foreach ($users as $user) {
                        $userId = $user['user_id'] ?? null;
                        if ($userId && in_array($userId, $ownerIds)) {
                            $ownerDetails[$userId] = [
                                'user_id' => $userId,
                                'username' => $user['username'] ?? null,
                                'display_name' => $user['display_name'] ?? null,
                                'avatar' => $user['avatar'] ?? null,
                                'metadata' => $user['metadata'] ?? [],
                                'team_name' => $user['metadata']['team_name']
                                    ?? $user['display_name']
                                    ?? $user['username']
                                    ?? 'Team '.$userId,
                            ];
                        }
                    }
                }
            } else {
                logger('FetchRostersTool: Failed to fetch league users', [
                    'league_id' => $leagueId,
                ]);
            }
        } catch (\Exception $e) {
            logger('FetchRostersTool: Exception fetching league users', [
                'league_id' => $leagueId,
                'error' => $e->getMessage(),
            ]);
        }

        // Ensure every requested owner ID has an entry
        foreach ($ownerIds as $ownerId) {
            if (! isset($ownerDetails[$ownerId])) {
                $ownerDetails[$ownerId] = [
                    'user_id' => $ownerId,
                    'username' => null,
                    'display_name' => null,
                    'avatar' => null,
                    'metadata' => [],
                    'team_name' => 'Team '.$ownerId,
                ];
            }
        }

        return $ownerDetails;
    }
}
