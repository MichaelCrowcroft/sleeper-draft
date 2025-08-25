<?php

namespace App\MCP\Tools\Utils;

use App\Services\EspnSdk;
use App\Services\SleeperSdk;
use Illuminate\Support\Facades\App as LaravelApp;
use OPGG\LaravelMcpServer\Services\ToolService\ToolInterface;

class DataDiagnosticTool implements ToolInterface
{
    public function name(): string
    {
        return 'data_diagnostic';
    }

    public function description(): string
    {
        return 'Diagnostic tool to inspect raw data from Sleeper and ESPN APIs for quality analysis.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                'sport' => ['type' => 'string', 'default' => 'nfl'],
                'season' => ['type' => 'string'],
                'week' => ['type' => 'integer', 'minimum' => 1],
                'sample_players' => ['type' => 'array', 'items' => ['type' => 'string'], 'default' => ['Josh Allen', 'Christian McCaffrey', 'Tyreek Hill', 'Austin Ekeler', 'Cooper Kupp']],
                'check_adp' => ['type' => 'boolean', 'default' => true],
                'check_projections' => ['type' => 'boolean', 'default' => true],
                'check_espn' => ['type' => 'boolean', 'default' => true],
                'limit' => ['type' => 'integer', 'default' => 50],
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
        /** @var EspnSdk $espn */
        $espn = LaravelApp::make(EspnSdk::class);

        $sport = $arguments['sport'] ?? 'nfl';
        $season = $arguments['season'] ?? date('Y');
        $week = (int) ($arguments['week'] ?? 1);
        $samplePlayers = $arguments['sample_players'] ?? ['Josh Allen', 'Christian McCaffrey', 'Tyreek Hill', 'Austin Ekeler', 'Cooper Kupp'];
        $checkAdp = (bool) ($arguments['check_adp'] ?? true);
        $checkProjections = (bool) ($arguments['check_projections'] ?? true);
        $checkEspn = (bool) ($arguments['check_espn'] ?? true);
        $limit = (int) ($arguments['limit'] ?? 50);

        $results = [
            'timestamp' => now()->toISOString(),
            'season' => $season,
            'week' => $week,
            'data_quality_checks' => [],
        ];

        // Get player catalog for name lookups
        $catalog = $sdk->getPlayersCatalog($sport);
        $nameToId = [];
        $idToMeta = [];
        foreach ($catalog as $playerId => $meta) {
            $fullName = trim(($meta['first_name'] ?? '').' '.($meta['last_name'] ?? ''));
            if (!empty($fullName)) {
                $nameToId[strtolower($fullName)] = $playerId;
            }
            $idToMeta[$playerId] = $meta;
        }

        // Check ADP data quality
        if ($checkAdp) {
            $results['data_quality_checks']['adp'] = $this->checkAdpQuality($sdk, $sport, $season, $samplePlayers, $nameToId, $idToMeta, $limit);
        }

        // Check projections data quality
        if ($checkProjections) {
            $results['data_quality_checks']['projections'] = $this->checkProjectionsQuality($sdk, $sport, $season, $week, $samplePlayers, $nameToId, $idToMeta, $limit);
        }

        // Check ESPN data quality
        if ($checkEspn) {
            $results['data_quality_checks']['espn'] = $this->checkEspnQuality($espn, (int) $season, $samplePlayers, $nameToId, $idToMeta, $limit);
        }

        return $results;
    }

    private function checkAdpQuality(SleeperSdk $sdk, string $sport, string $season, array $samplePlayers, array $nameToId, array $idToMeta, int $limit): array
    {
        $adp = $sdk->getAdp($season, 'redraft', $sport, ttlSeconds: 0); // Force fresh data

        $result = [
            'total_players' => count($adp),
            'sample_analysis' => [],
            'data_freshness' => [
                'has_data' => !empty($adp),
                'top_10_players' => [],
            ],
            'quality_issues' => [],
        ];

        // Analyze top players
        $topPlayers = array_slice($adp, 0, min(10, count($adp)));
        foreach ($topPlayers as $i => $player) {
            $playerId = $player['player_id'];
            $meta = $idToMeta[$playerId] ?? null;
            $result['data_freshness']['top_10_players'][] = [
                'rank' => $i + 1,
                'name' => $meta ? trim(($meta['first_name'] ?? '').' '.($meta['last_name'] ?? '')) : 'Unknown',
                'position' => $meta['position'] ?? 'Unknown',
                'team' => $meta['team'] ?? 'Unknown',
                'adp' => $player['adp'],
                'source' => $player['source'] ?? 'unknown',
            ];
        }

        // Analyze sample players
        foreach ($samplePlayers as $sampleName) {
            $sampleNameLower = strtolower($sampleName);
            $playerId = $nameToId[$sampleNameLower] ?? null;

            if (!$playerId) {
                $result['sample_analysis'][] = [
                    'name' => $sampleName,
                    'found' => false,
                    'issue' => 'Player not found in catalog',
                ];
                continue;
            }

            // Find ADP for this player
            $playerAdp = null;
            foreach ($adp as $adpEntry) {
                if ($adpEntry['player_id'] === $playerId) {
                    $playerAdp = $adpEntry;
                    break;
                }
            }

            $meta = $idToMeta[$playerId];
            $result['sample_analysis'][] = [
                'name' => $sampleName,
                'found' => true,
                'position' => $meta['position'] ?? 'Unknown',
                'team' => $meta['team'] ?? 'Unknown',
                'adp' => $playerAdp ? $playerAdp['adp'] : null,
                'adp_source' => $playerAdp ? ($playerAdp['source'] ?? 'unknown') : 'not_found',
                'has_adp' => $playerAdp !== null,
            ];
        }

        // Check for quality issues
        if (empty($adp)) {
            $result['quality_issues'][] = 'No ADP data available - possible API issue';
        }

        if (count($adp) < 100) {
            $result['quality_issues'][] = 'Very limited ADP data - only ' . count($adp) . ' players';
        }

        // Check if using fallback data
        $fallbackCount = 0;
        foreach ($adp as $player) {
            if (isset($player['source']) && $player['source'] === 'fallback_trending') {
                $fallbackCount++;
            }
        }
        if ($fallbackCount > 0) {
            $result['quality_issues'][] = "Using fallback trending data for $fallbackCount players - ADP may be inaccurate";
        }

        return $result;
    }

    private function checkProjectionsQuality(SleeperSdk $sdk, string $sport, string $season, int $week, array $samplePlayers, array $nameToId, array $idToMeta, int $limit): array
    {
        $projections = $sdk->getWeeklyProjections($season, $week, $sport, ttlSeconds: 0);

        $result = [
            'total_players' => count($projections),
            'sample_analysis' => [],
            'data_freshness' => [
                'has_data' => !empty($projections),
                'top_projected' => [],
            ],
            'quality_issues' => [],
        ];

        // Find top projected players
        $projectedWithPoints = [];
        foreach ($projections as $playerId => $proj) {
            $pts = (float) (($proj['pts_half_ppr'] ?? $proj['pts_ppr'] ?? $proj['pts_std'] ?? 0));
            if ($pts > 0) {
                $projectedWithPoints[] = [
                    'player_id' => $playerId,
                    'points' => $pts,
                    'meta' => $idToMeta[$playerId] ?? null,
                ];
            }
        }

        usort($projectedWithPoints, fn($a, $b) => $b['points'] <=> $a['points']);
        $topProjected = array_slice($projectedWithPoints, 0, min(10, count($projectedWithPoints)));

        foreach ($topProjected as $i => $player) {
            $meta = $player['meta'];
            $result['data_freshness']['top_projected'][] = [
                'rank' => $i + 1,
                'name' => $meta ? trim(($meta['first_name'] ?? '').' '.($meta['last_name'] ?? '')) : 'Unknown',
                'position' => $meta['position'] ?? 'Unknown',
                'team' => $meta['team'] ?? 'Unknown',
                'projected_points' => $player['points'],
            ];
        }

        // Analyze sample players
        foreach ($samplePlayers as $sampleName) {
            $sampleNameLower = strtolower($sampleName);
            $playerId = $nameToId[$sampleNameLower] ?? null;

            if (!$playerId) {
                $result['sample_analysis'][] = [
                    'name' => $sampleName,
                    'found' => false,
                    'issue' => 'Player not found in catalog',
                ];
                continue;
            }

            $proj = $projections[$playerId] ?? null;
            $meta = $idToMeta[$playerId];

            $points = null;
            if ($proj) {
                $points = (float) (($proj['pts_half_ppr'] ?? $proj['pts_ppr'] ?? $proj['pts_std'] ?? 0));
            }

            $result['sample_analysis'][] = [
                'name' => $sampleName,
                'found' => true,
                'position' => $meta['position'] ?? 'Unknown',
                'team' => $meta['team'] ?? 'Unknown',
                'has_projections' => $proj !== null,
                'projected_points_half_ppr' => $proj['pts_half_ppr'] ?? null,
                'projected_points_ppr' => $proj['pts_ppr'] ?? null,
                'projected_points_std' => $proj['pts_std'] ?? null,
                'projected_points_used' => $points,
            ];
        }

        // Check for quality issues
        if (empty($projections)) {
            $result['quality_issues'][] = 'No projections data available - possible API issue';
        }

        if (count($projectedWithPoints) < 50) {
            $result['quality_issues'][] = 'Very limited projections data - only ' . count($projectedWithPoints) . ' players with projections';
        }

        return $result;
    }

    private function checkEspnQuality(EspnSdk $espn, int $season, array $samplePlayers, array $nameToId, array $idToMeta, int $limit): array
    {
        $espnPlayers = $espn->getFantasyPlayers($season, 'mDraftDetail', $limit, ttlSeconds: 0);

        $result = [
            'total_players' => count($espnPlayers),
            'sample_analysis' => [],
            'data_freshness' => [
                'has_data' => !empty($espnPlayers),
                'top_ranked' => [],
            ],
            'quality_issues' => [],
        ];

        // Find top ESPN players
        $playersWithAdp = [];
        foreach ($espnPlayers as $player) {
            $adp = $player['averageDraftPosition'] ?? $player['draftRanksByRankType']['PPR']['rank'] ?? null;
            if ($adp && is_numeric($adp)) {
                $playersWithAdp[] = [
                    'player' => $player,
                    'adp' => (float) $adp,
                ];
            }
        }

        usort($playersWithAdp, fn($a, $b) => $a['adp'] <=> $b['adp']);
        $topEspn = array_slice($playersWithAdp, 0, min(10, count($playersWithAdp)));

        foreach ($topEspn as $i => $entry) {
            $player = $entry['player'];
            $result['data_freshness']['top_ranked'][] = [
                'rank' => $i + 1,
                'name' => $player['fullName'] ?? 'Unknown',
                'position' => $player['defaultPositionId'] ?? 'Unknown',
                'team' => $player['proTeamId'] ?? 'Unknown',
                'espn_adp' => $entry['adp'],
            ];
        }

        // Analyze sample players
        foreach ($samplePlayers as $sampleName) {
            $sampleNameLower = strtolower($sampleName);

            // Try to find player in ESPN data by name
            $espnPlayer = null;
            foreach ($espnPlayers as $player) {
                $espnName = strtolower($player['fullName'] ?? '');
                if ($espnName === $sampleNameLower) {
                    $espnPlayer = $player;
                    break;
                }
            }

            $result['sample_analysis'][] = [
                'name' => $sampleName,
                'found_in_espn' => $espnPlayer !== null,
                'espn_adp' => $espnPlayer ? ($espnPlayer['averageDraftPosition'] ?? null) : null,
                'espn_rank_ppr' => $espnPlayer ? ($espnPlayer['draftRanksByRankType']['PPR']['rank'] ?? null) : null,
                'espn_id' => $espnPlayer['id'] ?? null,
            ];
        }

        // Check for quality issues
        if (empty($espnPlayers)) {
            $result['quality_issues'][] = 'No ESPN data available - possible API issue';
        }

        if (count($espnPlayers) < 100) {
            $result['quality_issues'][] = 'Very limited ESPN data - only ' . count($espnPlayers) . ' players';
        }

        return $result;
    }
}
