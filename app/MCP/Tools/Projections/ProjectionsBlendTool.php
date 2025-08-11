<?php

namespace App\MCP\Tools\Projections;

use App\Services\SleeperSdk;
use Illuminate\Support\Facades\App as LaravelApp;
use OPGG\LaravelMcpServer\Services\ToolService\ToolInterface;

class ProjectionsBlendTool implements ToolInterface
{
    public function name(): string
    {
        return 'projections_blend';
    }

    public function description(): string
    {
        return 'Blend multiple projection sources (currently single-source placeholder with configurable weights).';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['season','week'],
            'properties' => [
                'sport' => ['type' => 'string', 'default' => 'nfl'],
                'season' => ['type' => 'string'],
                'week' => ['type' => 'integer', 'minimum' => 1],
                'weights' => ['type' => 'object'],
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
        /** @var SleeperSdk $sdk */
        $sdk = LaravelApp::make(SleeperSdk::class);
        $sport = $arguments['sport'] ?? 'nfl';
        $season = (string) $arguments['season'];
        $week = (int) $arguments['week'];

        $projections = $sdk->getWeeklyProjections($season, $week, $sport);
        // Placeholder blend: pass-through; expose in standard shape
        $blended = [];
        foreach ($projections as $pid => $row) {
            $blended[$pid] = [
                'median' => (float) ($row['pts_half_ppr'] ?? $row['pts_ppr'] ?? $row['pts_std'] ?? 0),
                'floor' => (float) (($row['pts_floor'] ?? 0)),
                'ceiling' => (float) (($row['pts_ceiling'] ?? ($row['pts_half_ppr'] ?? 0))),
            ];
        }

        return ['blended' => $blended];
    }
}
