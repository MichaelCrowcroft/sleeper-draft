<?php

namespace App\MCP\Tools\Utils;

use App\Services\EspnSdk;
use App\Services\SleeperSdk;
use Illuminate\Support\Facades\App as LaravelApp;
use Illuminate\Support\Facades\Http;
use OPGG\LaravelMcpServer\Services\ToolService\ToolInterface;

class ExternalDataComparisonTool implements ToolInterface
{
    public function name(): string
    {
        return 'external_data_comparison';
    }

    public function description(): string
    {
        return 'Compare our data against external sources to validate accuracy.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                'season' => ['type' => 'string', 'default' => '2025'],
                'sample_players' => ['type' => 'array', 'items' => ['type' => 'string'], 'default' => [
                    'Josh Allen', 'Christian McCaffrey', 'Tyreek Hill', 'Austin Ekeler', 'Cooper Kupp',
                    'Patrick Mahomes', 'Lamar Jackson', 'Dak Prescott', 'Stefon Diggs', 'Davante Adams'
                ]],
                'check_external_adp' => ['type' => 'boolean', 'default' => true],
                'check_projections' => ['type' => 'boolean', 'default' => true],
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
        $season = $arguments['season'] ?? '2025';
        $samplePlayers = $arguments['sample_players'] ?? [
            'Josh Allen', 'Christian McCaffrey', 'Tyreek Hill', 'Austin Ekeler', 'Cooper Kupp',
            'Patrick Mahomes', 'Lamar Jackson', 'Dak Prescott', 'Stefon Diggs', 'Davante Adams'
        ];

        /** @var SleeperSdk $sdk */
        $sdk = LaravelApp::make(SleeperSdk::class);

        $results = [
            'timestamp' => now()->toISOString(),
            'season' => $season,
            'data_comparison' => [],
        ];

        // Get our current data
        $catalog = $sdk->getPlayersCatalog('nfl');
        $adp = $sdk->getAdp($season, 'redraft', 'nfl', ttlSeconds: 0);
        $projections = $sdk->getWeeklyProjections($season, 1, 'nfl', ttlSeconds: 0);

        // Build player lookup maps
        $nameToId = [];
        $idToMeta = [];
        foreach ($catalog as $playerId => $meta) {
            $fullName = trim(($meta['first_name'] ?? '').' '.($meta['last_name'] ?? ''));
            if (!empty($fullName)) {
                $nameToId[strtolower($fullName)] = $playerId;
            }
            $idToMeta[$playerId] = $meta;
        }

        // Get ADP data for sample players
        $adpData = [];
        foreach ($samplePlayers as $playerName) {
            $playerNameLower = strtolower($playerName);
            $playerId = $nameToId[$playerNameLower] ?? null;

            $ourAdp = null;
            if ($playerId && isset($adp[$playerId])) {
                $ourAdp = $adp[$playerId]['adp'] ?? null;
            }

            $meta = $playerId ? ($idToMeta[$playerId] ?? null) : null;

            $adpData[$playerName] = [
                'our_adp' => $ourAdp,
                'our_rank' => $ourAdp,
                'player_id' => $playerId,
                'position' => $meta['position'] ?? 'Unknown',
                'team' => $meta['team'] ?? 'Unknown',
            ];
        }

        if ($arguments['check_external_adp'] ?? true) {
            $results['data_comparison']['adp_comparison'] = $this->compareADPData($adpData);
        }

        if ($arguments['check_projections'] ?? true) {
            $results['data_comparison']['projections_comparison'] = $this->compareProjectionsData($samplePlayers, $nameToId, $projections, $idToMeta);
        }

        return $results;
    }

    private function compareADPData(array $adpData): array
    {
        $comparison = [
            'methodology' => 'Comparing our ADP data against known consensus rankings',
            'expected_top_10_qb' => ['Josh Allen', 'Patrick Mahomes', 'Lamar Jackson', 'Dak Prescott', 'Jalen Hurts'],
            'expected_top_10_rb' => ['Christian McCaffrey', 'Austin Ekeler', 'Tyreek Hill', 'Cooper Kupp', 'Stefon Diggs'],
            'our_rankings_analysis' => [],
            'issues_found' => [],
        ];

        foreach ($adpData as $playerName => $data) {
            $ourRank = $data['our_adp'];

            // Determine expected approximate rank based on player
            $expectedRank = $this->getExpectedRank($playerName);

            $comparison['our_rankings_analysis'][$playerName] = [
                'our_rank' => $ourRank,
                'expected_rank_range' => $expectedRank,
                'position' => $data['position'],
                'team' => $data['team'],
                'deviation' => $ourRank ? abs($ourRank - $expectedRank['mid']) : 'No data',
                'is_problematic' => $this->isProblematicRanking($playerName, $ourRank),
            ];
        }

        // Check for major issues
        $problematicCount = 0;
        foreach ($comparison['our_rankings_analysis'] as $analysis) {
            if ($analysis['is_problematic']) {
                $problematicCount++;
                $comparison['issues_found'][] = "Player {$analysis['name']} has problematic ranking";
            }
        }

        if ($problematicCount > 5) {
            $comparison['issues_found'][] = "Major ADP data corruption detected - most top players have incorrect rankings";
        }

        return $comparison;
    }

    private function compareProjectionsData(array $samplePlayers, array $nameToId, array $projections, array $idToMeta): array
    {
        $comparison = [
            'methodology' => 'Checking if projections data exists and is reasonable',
            'issues_found' => [],
            'projections_analysis' => [],
        ];

        foreach ($samplePlayers as $playerName) {
            $playerNameLower = strtolower($playerName);
            $playerId = $nameToId[$playerNameLower] ?? null;

            $hasProjections = false;
            $projectionPoints = null;

            if ($playerId && isset($projections[$playerId])) {
                $proj = $projections[$playerId];
                $projectionPoints = (float) (($proj['pts_half_ppr'] ?? $proj['pts_ppr'] ?? $proj['pts_std'] ?? 0));
                $hasProjections = $projectionPoints > 0;
            }

            $meta = $playerId ? ($idToMeta[$playerId] ?? null) : null;

            $comparison['projections_analysis'][$playerName] = [
                'has_projections' => $hasProjections,
                'projected_points' => $projectionPoints,
                'position' => $meta['position'] ?? 'Unknown',
                'is_problematic' => !$hasProjections,
            ];
        }

        // Check for systemic issues
        $totalPlayers = count($samplePlayers);
        $playersWithoutProjections = 0;

        foreach ($comparison['projections_analysis'] as $analysis) {
            if (!$analysis['has_projections']) {
                $playersWithoutProjections++;
            }
        }

        if ($playersWithoutProjections === $totalPlayers) {
            $comparison['issues_found'][] = "No projections data available for any players - systemic issue";
        } elseif ($playersWithoutProjections > $totalPlayers * 0.7) {
            $comparison['issues_found'][] = "Most players missing projections data";
        }

        return $comparison;
    }

    private function getExpectedRank(string $playerName): array
    {
        $rankings = [
            'Josh Allen' => ['min' => 1, 'mid' => 3, 'max' => 6],
            'Patrick Mahomes' => ['min' => 1, 'mid' => 2, 'max' => 5],
            'Lamar Jackson' => ['min' => 1, 'mid' => 4, 'max' => 8],
            'Dak Prescott' => ['min' => 5, 'mid' => 8, 'max' => 12],
            'Christian McCaffrey' => ['min' => 1, 'mid' => 1, 'max' => 2],
            'Austin Ekeler' => ['min' => 2, 'mid' => 3, 'max' => 5],
            'Tyreek Hill' => ['min' => 1, 'mid' => 2, 'max' => 4],
            'Cooper Kupp' => ['min' => 3, 'mid' => 5, 'max' => 8],
            'Stefon Diggs' => ['min' => 6, 'mid' => 10, 'max' => 15],
            'Davante Adams' => ['min' => 4, 'mid' => 7, 'max' => 12],
        ];

        return $rankings[$playerName] ?? ['min' => 100, 'mid' => 150, 'max' => 200];
    }

    private function isProblematicRanking(string $playerName, ?int $ourRank): bool
    {
        if (!$ourRank) {
            return true; // No ranking data is problematic
        }

        $expected = $this->getExpectedRank($playerName);

        // If our rank is way outside the expected range, it's problematic
        return $ourRank < $expected['min'] - 20 || $ourRank > $expected['max'] + 20;
    }
}
