<?php

namespace App\MCP\Tools;

use App\Actions\Sleeper\GetSeasonState;
use MichaelCrowcroft\SleeperLaravel\Facades\Sleeper;
use OPGG\LaravelMcpServer\Services\ToolService\ToolInterface;

class FetchUserLeaguesTool implements ToolInterface
{
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
        $user_identifier = $arguments['user_identifier'] ?? null;
        $sport = $arguments['sport'] ?? 'nfl';
        $season = $arguments['season'] ?? null;

        if (!$user_identifier) {
            return [
                'success' => false,
                'error' => 'Missing required parameter: user_identifier',
                'message' => 'The user_identifier parameter is required',
            ];
        }

        if ($season === null) {
            $state = new GetSeasonState()->execute($sport);
            $season = $state['season'];
        }

        if(is_numeric($user_identifier)) {
            $user_id = $user_identifier;
        } else {
            $response = Sleeper::users()->get($user_identifier, $sport);
            $user = $response->json();
            $user_id = $user['user_id'];
        }
        $leagues = Sleeper::user($user_id)
            ->leagues($sport, $season)
            ->json();


            return [
                'success' => true,
                'data' => $leagues,
                'count' => count($leagues),
                'message' => 'Successfully fetched '.count($leagues)." leagues for user '{$user_identifier}'",
                'metadata' => [
                    'resolved_user_id' => $user_id,
                    'sport' => $sport,
                    'season' => $season,
                    'executed_at' => now()->toISOString(),
                ],
            ];

    }
}
