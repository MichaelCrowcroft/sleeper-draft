<?php

namespace App\MCP\Tools;

use App\Actions\Sleeper\FetchLeague as FetchLeagueAction;
use App\Actions\Sleeper\FetchLeagueUsers;
use App\Actions\Sleeper\FetchRosters;
use App\Actions\Sleeper\FetchUserLeagues as FetchUserLeaguesAction;
use App\MCP\Support\ToolHelpers;
use OPGG\LaravelMcpServer\Exceptions\Enums\JsonRpcErrorCode;
use OPGG\LaravelMcpServer\Exceptions\JsonRpcErrorException;
use OPGG\LaravelMcpServer\Services\ToolService\ToolInterface;

class FetchLeagueTool implements ToolInterface
{
    use ToolHelpers;

    public function isStreaming(): bool
    {
        return false;
    }

    public function name(): string
    {
        return 'fetch-league';
    }

    public function description(): string
    {
        return 'Get League tool fetches all leagues for a user and returns one league based on the name or ID. It also fetches all users in the league and returns user information with username, display name, and team names.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'user_id' => [
                    'type' => 'string',
                    'description' => 'Sleeper user ID to fetch leagues for',
                ],
                'league_identifier' => [
                    'type' => 'string',
                    'description' => 'League name or league ID to find and return',
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
            'required' => ['user_id', 'league_identifier'],
        ];
    }

    public function annotations(): array
    {
        return [];
    }

    public function execute(array $arguments): mixed
    {
        // Normalize and validate
        $args = $this->normalizeArgumentsGeneric(
            $arguments,
            aliases: ['userId' => 'user_id', 'leagueId' => 'league_identifier'],
            stringKeys: ['user_id', 'league_identifier', 'sport', 'season']
        );

        $userId = $args['user_id'];
        $leagueIdentifier = $args['league_identifier'];
        $sport = $args['sport'] ?? 'nfl';
        $season = $args['season'] ?? $this->getCurrentSeason($sport);

        // Get user's leagues and find the target league (via Action)
        $leagues = app(FetchUserLeaguesAction::class)->execute($userId, $sport, (int) $season);
        $targetLeague = collect($leagues)->first(fn ($league) => (
            ($league['league_id'] ?? null) === $leagueIdentifier
        ) || strcasecmp($league['name'] ?? '', (string) $leagueIdentifier) === 0);

        if (! $targetLeague) {
            throw new JsonRpcErrorException(
                message: "League '{$leagueIdentifier}' not found",
                code: JsonRpcErrorCode::INVALID_REQUEST
            );
        }

        $leagueId = $targetLeague['league_id'];

        // Get league details and users with rosters via Actions
        $league = app(FetchLeagueAction::class)->execute($leagueId);
        $users = app(FetchLeagueUsers::class)->execute($leagueId);
        $rosters = app(FetchRosters::class)->execute($leagueId);

        // Map rosters to users
        $rostersByUserId = collect($rosters)->keyBy('owner_id');
        $enhancedUsers = collect($users)->map(function ($user) use ($rostersByUserId) {
            $userId = $user['user_id'];
            $roster = $rostersByUserId->get($userId);

            return [
                'user_id' => $userId,
                'username' => $user['username'] ?? null,
                'display_name' => $user['display_name'] ?? null,
                'team_name' => $user['metadata']['team_name'] ?? $user['display_name'] ?? $user['username'] ?? "Team {$userId}",
                'wins' => $roster['settings']['wins'] ?? 0,
                'losses' => $roster['settings']['losses'] ?? 0,
                'fpts' => $roster['settings']['fpts'] ?? 0,
            ];
        });

        $usersArray = $enhancedUsers->values()->all();

        return $this->buildResponse(
            data: [
                'league' => $league,
                'users' => $usersArray,
            ],
            count: count($usersArray),
            message: 'Fetched league details and users',
            metadata: [
                'league_id' => $leagueId,
                'season' => $season,
                'sport' => $sport,
            ]
        );
    }

    // getCurrentSeason provided by ToolHelpers
}
