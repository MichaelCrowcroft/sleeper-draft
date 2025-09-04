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
        return 'Fetches rosters for a league using the Sleeper SDK. Supports pagination and compact mode to prevent response truncation in chat platforms. Returns roster data with player information from the database and owner details when available.';
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
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Maximum number of rosters to return (1-50, helps prevent truncation)',
                    'minimum' => 1,
                    'maximum' => 50,
                ],
                'offset' => [
                    'type' => 'integer',
                    'description' => 'Number of rosters to skip (for pagination)',
                    'minimum' => 0,
                    'default' => 0,
                ],
                'compact' => [
                    'type' => 'boolean',
                    'description' => 'Return compact response format to reduce size',
                    'default' => false,
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
            'supports_pagination' => true,
            'handles_large_responses' => true,
            'truncation_safe' => true,
        ];
    }

    public function execute(array $arguments): mixed
    {
        // Validate input arguments
        $validator = Validator::make($arguments, [
            'league_id' => ['required', 'string'],
            'include_player_details' => ['nullable', 'boolean'],
            'include_owner_details' => ['nullable', 'boolean'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'offset' => ['nullable', 'integer', 'min:0'],
            'compact' => ['nullable', 'boolean'],
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
        $limit = $arguments['limit'] ?? null;
        $offset = $arguments['offset'] ?? 0;
        $compact = $arguments['compact'] ?? false;

        // Fetch rosters from Sleeper API
        $rosters = $this->fetchRosters($leagueId);

        // Apply pagination if requested
        $totalRosters = count($rosters);
        if ($limit !== null) {
            $rosters = array_slice($rosters, $offset, $limit);
        }

        // Enhance rosters with player and owner details
        $enhancedRosters = $this->enhanceRosters($leagueId, $rosters, $includePlayerDetails, $includeOwnerDetails);

        // Prepare structured response to avoid truncation
        $response = [
            'success' => true,
            'operation' => 'fetch_league_rosters',
            'data' => $enhancedRosters,
            'pagination' => [
                'total' => $totalRosters,
                'count' => count($enhancedRosters),
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => $limit !== null && ($offset + $limit) < $totalRosters,
            ],
            'metadata' => [
                'league_id' => $leagueId,
                'include_player_details' => $includePlayerDetails,
                'include_owner_details' => $includeOwnerDetails,
                'compact_mode' => $compact,
                'executed_at' => now()->toISOString(),
                'response_size_kb' => strlen(json_encode($enhancedRosters)) / 1024,
            ],
        ];

        // Add formatted summary for large responses
        if (count($enhancedRosters) > 5 || $compact) {
            $response['summary'] = $this->generateSummary($enhancedRosters, $includePlayerDetails);
        }

        return $response;
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

    private function generateSummary(array $rosters, bool $includePlayerDetails): array
    {
        $summary = [
            'total_rosters' => count($rosters),
            'rosters_with_owners' => 0,
            'total_players' => 0,
            'position_breakdown' => [],
            'team_names' => [],
        ];

        foreach ($rosters as $roster) {
            // Count rosters with owners
            if (! empty($roster['owner'])) {
                $summary['rosters_with_owners']++;
                $summary['team_names'][] = $roster['owner']['team_name'] ?? 'Unknown Team';
            }

            // Count players and positions if player details are included
            if ($includePlayerDetails) {
                $allPlayers = array_merge(
                    $roster['players_detailed'] ?? [],
                    $roster['starters_detailed'] ?? [],
                    $roster['bench_detailed'] ?? []
                );

                foreach ($allPlayers as $player) {
                    if (! empty($player['player_data'])) {
                        $summary['total_players']++;
                        $position = $player['player_data']['position'] ?? 'Unknown';
                        $summary['position_breakdown'][$position] = ($summary['position_breakdown'][$position] ?? 0) + 1;
                    }
                }
            }
        }

        // Sort position breakdown by count
        arsort($summary['position_breakdown']);

        return $summary;
    }
}
