<?php

namespace App\MCP\Tools;

use App\Actions\Sleeper\FetchUserLeagues as FetchUserLeaguesAction;
use App\MCP\Support\ToolHelpers;
use MichaelCrowcroft\SleeperLaravel\Facades\Sleeper;
use OPGG\LaravelMcpServer\Exceptions\Enums\JsonRpcErrorCode;
use OPGG\LaravelMcpServer\Exceptions\JsonRpcErrorException;
use OPGG\LaravelMcpServer\Services\ToolService\ToolInterface;

class FetchUserLeaguesTool implements ToolInterface
{
    use ToolHelpers;

    public function isStreaming(): bool
    {
        return false;
    }

    public function name(): string
    {
        return 'fetch-user-leagues';
    }

    public function description(): string
    {
        return 'Fetches all leagues for a user by username or user ID. Returns league IDs and names for the specified sport and season.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'user_identifier' => [
                    'type' => 'string',
                    'description' => 'Sleeper username or user ID to fetch leagues for',
                ],
                'sport' => [
                    'type' => 'string',
                    'description' => 'Sport type (default: nfl)',
                    'default' => 'nfl',
                ],
                'season' => [
                    'type' => 'string',
                    'description' => 'Season year (default: current season)',
                ],
            ],
            'required' => ['user_identifier'],
        ];
    }

    public function annotations(): array
    {
        return [
            'title' => 'Fetch User Leagues',
            'readOnlyHint' => true,
            'destructiveHint' => false,
            'idempotentHint' => true,
            'openWorldHint' => true, // Makes API calls to Sleeper

            // Custom annotations
            'category' => 'fantasy-sports',
            'data_source' => 'external_api',
            'cache_recommended' => false, // League data changes frequently
        ];
    }

    public function execute(array $arguments): mixed
    {
        $arguments = $this->normalizeArgumentsGeneric(
            $arguments,
            aliases: ['userId' => 'user_identifier', 'username' => 'user_identifier'],
            stringKeys: ['user_identifier', 'sport', 'season']
        );

        $this->validateOrFail($arguments, [
            'user_identifier' => ['required', 'string'],
            'sport' => ['nullable', 'string'],
            'season' => ['nullable', 'string'],
        ]);

        $userIdentifier = $arguments['user_identifier'];
        $sport = $arguments['sport'] ?? 'nfl';
        $season = $arguments['season'] ?? null;

        try {
            // If no season provided, get current season from shared helper
            if ($season === null) {
                $season = $this->getCurrentSeason($sport);
            }

            // Get user ID from identifier (could be username or user ID)
            $userId = $this->resolveUserId($userIdentifier, $sport);

            // Fetch user's leagues via Action (cached)
            $leagues = app(FetchUserLeaguesAction::class)->execute($userId, $sport, (int) $season);

            // Extract league IDs and names
            $leagueData = [];
            foreach ($leagues as $league) {
                if (isset($league['league_id']) && isset($league['name'])) {
                    $leagueData[] = [
                        'id' => $league['league_id'],
                        'name' => $league['name'],
                    ];
                }
            }

            return [
                'success' => true,
                'data' => $leagueData,
                'count' => count($leagueData),
                'message' => 'Successfully fetched '.count($leagueData)." leagues for user '{$userIdentifier}'",
                'metadata' => [
                    'user_identifier' => $userIdentifier,
                    'resolved_user_id' => $userId,
                    'sport' => $sport,
                    'season' => $season,
                    'executed_at' => now()->toISOString(),
                ],
            ];

        } catch (JsonRpcErrorException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new JsonRpcErrorException(
                message: 'An unexpected error occurred: '.$e->getMessage(),
                code: JsonRpcErrorCode::INTERNAL_ERROR
            );
        }
    }

    private function resolveUserId(string $userIdentifier, string $sport): string
    {
        // Check if the identifier is already a user ID (numeric)
        if (is_numeric($userIdentifier)) {
            return $userIdentifier;
        }

        // Try to find user by username
        $response = Sleeper::users()->get($userIdentifier, $sport);

        if (! $response->successful()) {
            throw new JsonRpcErrorException(
                message: 'Failed to fetch user by username: HTTP '.$response->status(),
                code: JsonRpcErrorCode::INVALID_REQUEST
            );
        }

        $user = $response->json();

        if (! is_array($user) || ! isset($user['user_id'])) {
            throw new JsonRpcErrorException(
                message: 'User not found or invalid response format.',
                code: JsonRpcErrorCode::INVALID_REQUEST
            );
        }

        return $user['user_id'];
    }
}
