<?php

namespace App\MCP\Tools;

use App\Actions\Rosters\FetchEnhancedRoster as FetchEnhancedRosterAction;
use App\MCP\Support\ToolHelpers;
use Illuminate\Support\Facades\Validator;
use MichaelCrowcroft\SleeperLaravel\Facades\Sleeper;
use OPGG\LaravelMcpServer\Exceptions\Enums\JsonRpcErrorCode;
use OPGG\LaravelMcpServer\Exceptions\JsonRpcErrorException;
use OPGG\LaravelMcpServer\Services\ToolService\ToolInterface;

class FetchRosterTool implements ToolInterface
{
    use ToolHelpers;

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
        $arguments = $this->normalizeArgumentsGeneric(
            $arguments,
            aliases: [
                'leagueId' => 'league_id',
                'userId' => 'user_id',
                'includePlayerDetails' => 'include_player_details',
                'includeOwnerDetails' => 'include_owner_details',
            ],
            boolKeys: ['include_player_details', 'include_owner_details'],
            stringKeys: ['league_id', 'user_id']
        );
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

        // Enhance roster with player and owner details via Action
        $enhancedRoster = app(FetchEnhancedRosterAction::class)
            ->execute($leagueId, $roster, (bool) $includePlayerDetails, (bool) $includeOwnerDetails);

        $count = isset($enhancedRoster['players']) && is_array($enhancedRoster['players'])
            ? count($enhancedRoster['players'])
            : null;

        return $this->buildResponse(
            data: $enhancedRoster,
            count: $count,
            message: sprintf('Successfully fetched roster for user %s in league %s', $userId, $leagueId),
            metadata: [
                'league_id' => $leagueId,
                'user_id' => $userId,
                'include_player_details' => $includePlayerDetails,
                'include_owner_details' => $includeOwnerDetails,
            ]
        );
    }

    /**
     * Normalize loosely formatted argument payloads into a structured array.
     */
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

    // Enrichment moved to Actions\Rosters\FetchEnhancedRoster
}
