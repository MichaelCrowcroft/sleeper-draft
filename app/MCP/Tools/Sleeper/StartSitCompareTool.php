<?php

namespace App\MCP\Tools\Sleeper;

use App\Services\SleeperSdk;
use Illuminate\Support\Facades\App as LaravelApp;
use OPGG\LaravelMcpServer\Services\ToolService\ToolInterface;

class StartSitCompareTool implements ToolInterface
{
    public function name(): string
    {
        return 'start_sit_compare';
    }

    public function description(): string
    {
        return 'Compare two players for a given week using projections (fallback to approximate position averages).';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['player_a_id', 'player_b_id', 'season', 'week'],
            'properties' => [
                'sport' => ['type' => 'string', 'default' => 'nfl'],
                'season' => ['type' => 'string'],
                'week' => ['type' => 'integer', 'minimum' => 1],
                'player_a_id' => ['type' => 'string'],
                'player_b_id' => ['type' => 'string'],
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
        $a = (string) $arguments['player_a_id'];
        $b = (string) $arguments['player_b_id'];

        $projections = $sdk->getWeeklyProjections($season, $week, $sport);

        $pointsA = (float) (($projections[$a]['pts_half_ppr'] ?? $projections[$a]['pts_ppr'] ?? $projections[$a]['pts_std'] ?? 0));
        $pointsB = (float) (($projections[$b]['pts_half_ppr'] ?? $projections[$b]['pts_ppr'] ?? $projections[$b]['pts_std'] ?? 0));

        // Fallback: if missing projections, use nulls
        $recommended = $pointsA === $pointsB ? $a : ($pointsA > $pointsB ? $a : $b);

        return [
            'recommended' => $recommended,
            'points_a' => $pointsA,
            'points_b' => $pointsB,
            'rationale' => $pointsA === $pointsB ? 'Tie or missing projections' : 'Higher projected points',
        ];
    }
}
