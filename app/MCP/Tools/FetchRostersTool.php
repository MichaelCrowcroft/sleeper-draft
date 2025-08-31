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
        $enhancedRosters = $this->enhanceRosters($rosters, $includePlayerDetails, $includeOwnerDetails);

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

    private function enhanceRosters(array $rosters, bool $includePlayerDetails, bool $includeOwnerDetails): array
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

        // Fetch player data from database
        $playersFromDb = [];
        if (! empty($allPlayerIds) && $includePlayerDetails) {
            $playersFromDb = Player::whereIn('player_id', $allPlayerIds)
                ->get()
                ->keyBy('player_id');
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
            $ownerDetails = $this->fetchOwnerDetails($ownerIds);
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

    private function enhancePlayerArray(array $playerIds, $playersFromDb): array
    {
        $enhancedPlayers = [];

        foreach ($playerIds as $playerId) {
            $player = $playersFromDb[$playerId] ?? null;
            $playerData = $player ? new PlayerResource($player) : null;

            $enhancedPlayers[] = [
                'player_id' => $playerId,
                'player_data' => $playerData,
            ];
        }

        return $enhancedPlayers;
    }

    private function fetchOwnerDetails(array $ownerIds): array
    {
        $ownerDetails = [];

        foreach ($ownerIds as $ownerId) {
            try {
                $response = Sleeper::users()->get($ownerId);

                if ($response->successful()) {
                    $userData = $response->json();
                    if (is_array($userData)) {
                        $ownerDetails[$ownerId] = [
                            'user_id' => $userData['user_id'] ?? $ownerId,
                            'username' => $userData['username'] ?? null,
                            'display_name' => $userData['display_name'] ?? null,
                            'avatar' => $userData['avatar'] ?? null,
                            'metadata' => $userData['metadata'] ?? [],
                            'team_name' => $userData['metadata']['team_name'] ?? $userData['display_name'] ?? $userData['username'] ?? 'Team '.$ownerId,
                        ];
                    }
                } else {
                    // If individual user fetch fails, set basic info
                    $ownerDetails[$ownerId] = [
                        'user_id' => $ownerId,
                        'username' => null,
                        'display_name' => null,
                        'avatar' => null,
                        'metadata' => [],
                        'team_name' => 'Team '.$ownerId,
                        'fetch_error' => 'Failed to fetch user details',
                    ];
                }
            } catch (\Exception $e) {
                // Log error but continue processing
                logger('FetchRostersTool: Failed to fetch owner details', [
                    'owner_id' => $ownerId,
                    'error' => $e->getMessage(),
                ]);

                $ownerDetails[$ownerId] = [
                    'user_id' => $ownerId,
                    'username' => null,
                    'display_name' => null,
                    'avatar' => null,
                    'metadata' => [],
                    'team_name' => 'Team '.$ownerId,
                    'fetch_error' => $e->getMessage(),
                ];
            }
        }

        return $ownerDetails;
    }
}
