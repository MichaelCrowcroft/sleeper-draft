<?php

namespace App\MCP\Tools\Sleeper;

use App\MCP\Tools\BaseTool;
use App\Services\SleeperSdk;
use Illuminate\Support\Facades\App as LaravelApp;

class LeagueGetTool extends BaseTool
{
    public function name(): string
    {
        return 'league_get';
    }

    public function description(): string
    {
        return 'Get Sleeper league metadata and settings by league_id.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['league_id'],
            'properties' => [
                'league_id' => ['type' => 'string'],
            ],
            'additionalProperties' => false,
        ];
    }

    public function annotations(): array
    {
        return [];
    }

    public function execute(array $arguments): mixed
    {
        // Validate required parameters
        $this->validateRequired($arguments, ['league_id']);

        $leagueId = $this->getParam($arguments, 'league_id', '', true);

        /** @var SleeperSdk $sdk */
        $sdk = LaravelApp::make(SleeperSdk::class);

        try {
            $league = $sdk->getLeague($leagueId);

            if (empty($league)) {
                return [
                    'success' => false,
                    'error' => 'League not found',
                    'league_id' => $leagueId,
                    'league' => null,
                    'settings' => new \stdClass,
                ];
            }

            return [
                'success' => true,
                'league' => $league,
                'settings' => $league['settings'] ?? new \stdClass,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to retrieve league: '.$e->getMessage(),
                'league_id' => $leagueId,
                'league' => null,
                'settings' => new \stdClass,
            ];
        }
    }
}
