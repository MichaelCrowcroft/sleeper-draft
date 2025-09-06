<?php

namespace App\MCP\Tools;

use App\Http\Resources\PlayerResource;
use App\Models\Player;
use Illuminate\Support\Facades\Validator;
use MichaelCrowcroft\SleeperLaravel\Facades\Sleeper;
use OPGG\LaravelMcpServer\Exceptions\Enums\JsonRpcErrorCode;
use OPGG\LaravelMcpServer\Exceptions\JsonRpcErrorException;
use OPGG\LaravelMcpServer\Services\ToolService\ToolInterface;

class FetchRosterTool implements ToolInterface
{
    public function isStreaming(): bool
    {
        return false;
    }

    public function name(): string
    {
        return 'fetch-roster';
    }

    public function description(): string
    {
        return 'Gets a specific roster for a user in a league. Returns roster data with player information from the database and owner details.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'league_id' => [
                    'type' => 'string',
                    'description' => 'Sleeper league ID',
                ],
                'user_id' => [
                    'type' => 'string',
                    'description' => 'Sleeper user ID whose roster to fetch',
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
            'required' => ['league_id', 'user_id'],
        ];
    }

    public function annotations(): array
    {
        return [
            'title' => 'Get User Roster',
            'readOnlyHint' => true,
            'destructiveHint' => false,
            'idempotentHint' => true,
            'openWorldHint' => true,

            // Custom annotations
            'category' => 'fantasy-sports',
            'data_source' => 'external_api_and_database',
            'cache_recommended' => true,
        ];
    }

    public function execute(array $arguments): mixed
    {
        $arguments = $this->normalizeArguments($arguments);
        // Validate input arguments
        $validator = Validator::make($arguments, [
            'league_id' => ['required', 'string'],
            'user_id' => ['required', 'string'],
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
        $userId = $arguments['user_id'];
        $includePlayerDetails = $arguments['include_player_details'] ?? true;
        $includeOwnerDetails = $arguments['include_owner_details'] ?? true;

        // Fetch the specific roster
        $roster = $this->getRoster($leagueId, $userId);

        if (! $roster) {
            throw new JsonRpcErrorException(
                message: "No roster found for user {$userId} in league {$leagueId}",
                code: JsonRpcErrorCode::INVALID_REQUEST
            );
        }

        // Enhance roster with player and owner details
        $enhancedRoster = $this->enhanceRoster($leagueId, $roster, $includePlayerDetails, $includeOwnerDetails);

        $response = [
            'success' => true,
            'operation' => 'get-roster',
            'formattedOutput' => sprintf('Successfully fetched roster for user %s in league %s', $userId, $leagueId),
            'data' => $enhancedRoster,
            'metadata' => [
                'league_id' => $leagueId,
                'user_id' => $userId,
                'include_player_details' => $includePlayerDetails,
                'include_owner_details' => $includeOwnerDetails,
                'executed_at' => now()->toISOString(),
                'mode' => 'formatted',
            ],
        ];

        return json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Normalize loosely formatted argument payloads into a structured array.
     */
    private function normalizeArguments(array $arguments): array
    {
        // Handle cases where a single raw string is provided (e.g., "Arguments league_id\"123\" include_player_detailstrue …")
        if (count($arguments) === 1 && array_key_exists(0, $arguments) && is_string($arguments[0])) {
            $raw = trim((string) $arguments[0]);

            // Strip optional leading label
            $raw = preg_replace('/^\s*Arguments\s*/i', '', $raw ?? '');

            // Try JSON first
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $arguments = $decoded;
            } else {
                // Fallback regex-based key/value extraction
                $pairs = [];
                $pattern = '/([A-Za-z0-9_]+)\s*(?::|=)?\s*(?:\"([^\"]*)\"|\'([^\']*)\'|([0-9]+)|(true|false|null))/i';
                if (preg_match_all($pattern, $raw, $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $match) {
                        $key = $match[1];
                        $value = null;
                        if (isset($match[2]) && $match[2] !== '') {
                            $value = $match[2];
                        } elseif (isset($match[3]) && $match[3] !== '') {
                            $value = $match[3];
                        } elseif (isset($match[4]) && $match[4] !== '') {
                            $value = (int) $match[4];
                        } elseif (isset($match[5]) && $match[5] !== '') {
                            $lower = strtolower($match[5]);
                            $value = $lower === 'true' ? true : ($lower === 'false' ? false : null);
                        }
                        $pairs[$key] = $value;
                    }
                }

                if ($pairs !== []) {
                    $arguments = $pairs;
                }
            }
        }

        // Map camelCase aliases to expected snake_case keys
        $aliases = [
            'leagueId' => 'league_id',
            'userId' => 'user_id',
            'includePlayerDetails' => 'include_player_details',
            'includeOwnerDetails' => 'include_owner_details',
        ];

        $normalized = [];
        foreach ($arguments as $key => $value) {
            $normalized[$aliases[$key] ?? $key] = $value;
        }

        // Coerce booleans if provided as strings
        foreach (['include_player_details', 'include_owner_details'] as $boolKey) {
            if (isset($normalized[$boolKey]) && is_string($normalized[$boolKey])) {
                $val = strtolower($normalized[$boolKey]);
                if ($val === 'true') {
                    $normalized[$boolKey] = true;
                } elseif ($val === 'false') {
                    $normalized[$boolKey] = false;
                }
            }
        }

        // Coerce identifiers to strings
        if (isset($normalized['league_id'])) {
            $normalized['league_id'] = (string) $normalized['league_id'];
        }
        if (isset($normalized['user_id'])) {
            $normalized['user_id'] = (string) $normalized['user_id'];
        }

        return $normalized;
    }

    private function getRoster(string $leagueId, string $userId): ?array
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

        // Find the specific roster for the user
        foreach ($rosters as $roster) {
            if (($roster['owner_id'] ?? null) === $userId) {
                return $roster;
            }
        }

        return null;
    }

    private function enhanceRoster(string $leagueId, array $roster, bool $includePlayerDetails, bool $includeOwnerDetails): array
    {
        $enhancedRoster = $roster;

        // Get all player IDs from this roster for batch database lookup
        $allPlayerIds = [];
        if ($includePlayerDetails) {
            $players = array_merge(
                $roster['players'] ?? [],
                $roster['starters'] ?? [],
                $roster['bench'] ?? []
            );
            $allPlayerIds = array_unique($players);
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

        // Add owner details if available
        if ($includeOwnerDetails && ! empty($roster['owner_id'])) {
            $enhancedRoster['owner'] = $this->getOwnerDetails($leagueId, $roster['owner_id']);
        }

        // Enhance player arrays with database information
        if ($includePlayerDetails) {
            $enhancedRoster['players_detailed'] = $this->enhancePlayerArray($roster['players'] ?? [], $playersFromDb);
            $enhancedRoster['starters_detailed'] = $this->enhancePlayerArray($roster['starters'] ?? [], $playersFromDb);
            $enhancedRoster['bench_detailed'] = $this->enhancePlayerArray($roster['bench'] ?? [], $playersFromDb);
        }

        return $enhancedRoster;
    }

    private function enhancePlayerArray(array $playerIds, array $playersFromDb): array
    {
        return array_map(fn ($playerId) => [
            'player_id' => $playerId,
            'player_data' => $playersFromDb[$playerId] ?? null,
        ], $playerIds);
    }

    private function getOwnerDetails(string $leagueId, string $ownerId): ?array
    {
        try {
            $response = Sleeper::leagues()->users($leagueId);

            if ($response->successful()) {
                $users = $response->json();

                if (is_array($users)) {
                    foreach ($users as $user) {
                        $userId = $user['user_id'] ?? null;
                        if ($userId === $ownerId) {
                            return [
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
                logger('GetRosterTool: Failed to fetch league users', [
                    'league_id' => $leagueId,
                ]);
            }
        } catch (\Exception $e) {
            logger('GetRosterTool: Exception fetching league users', [
                'league_id' => $leagueId,
                'error' => $e->getMessage(),
            ]);
        }

        // Return basic owner info if API call fails
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
