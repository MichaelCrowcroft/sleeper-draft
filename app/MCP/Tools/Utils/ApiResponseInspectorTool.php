<?php

namespace App\MCP\Tools\Utils;

use App\Integrations\Sleeper\Requests\GetAdp;
use App\Integrations\Sleeper\Requests\GetWeeklyProjections;
use App\Integrations\Sleeper\SleeperConnector;
use App\Integrations\Espn\Requests\GetFantasyPlayers;
use App\Integrations\Espn\EspnFantasyConnector;
use Illuminate\Support\Facades\App as LaravelApp;
use OPGG\LaravelMcpServer\Services\ToolService\ToolInterface;

class ApiResponseInspectorTool implements ToolInterface
{
    public function name(): string
    {
        return 'api_response_inspector';
    }

    public function description(): string
    {
        return 'Inspect raw API responses to diagnose data quality issues.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                'season' => ['type' => 'string', 'default' => '2025'],
                'week' => ['type' => 'integer', 'default' => 1],
                'check_sleeper_adp' => ['type' => 'boolean', 'default' => true],
                'check_sleeper_projections' => ['type' => 'boolean', 'default' => true],
                'check_espn' => ['type' => 'boolean', 'default' => true],
                'limit_output' => ['type' => 'boolean', 'default' => true],
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
        $week = (int) ($arguments['week'] ?? 1);
        $checkSleeperAdp = (bool) ($arguments['check_sleeper_adp'] ?? true);
        $checkSleeperProjections = (bool) ($arguments['check_sleeper_projections'] ?? true);
        $checkEspn = (bool) ($arguments['check_espn'] ?? true);
        $limitOutput = (bool) ($arguments['limit_output'] ?? true);

        $results = [
            'timestamp' => now()->toISOString(),
            'season' => $season,
            'week' => $week,
            'api_responses' => [],
        ];

        if ($checkSleeperAdp) {
            $results['api_responses']['sleeper_adp'] = $this->inspectSleeperAdp($season);
        }

        if ($checkSleeperProjections) {
            $results['api_responses']['sleeper_projections'] = $this->inspectSleeperProjections($season, $week);
        }

        if ($checkEspn) {
            $results['api_responses']['espn_fantasy'] = $this->inspectEspnFantasy((int) $season);
        }

        return $results;
    }

    private function inspectSleeperAdp(string $season): array
    {
        /** @var SleeperConnector $connector */
        $connector = LaravelApp::make(SleeperConnector::class);

        try {
            $response = $connector->send(new GetAdp('nfl', $season, 'redraft'));
            $data = $response->json();

            $result = [
                'endpoint' => "/v1/stats/nfl/{$season}/adp",
                'status' => $response->status(),
                'success' => $response->successful(),
                'data_type' => gettype($data),
                'data_size' => is_array($data) ? count($data) : strlen($data ?? ''),
            ];

            if ($result['success'] && is_array($data)) {
                $sampleKeys = array_slice(array_keys($data), 0, 5);
                $result['sample_keys'] = $sampleKeys;

                if (!empty($data)) {
                    $firstKey = $sampleKeys[0];
                    $firstPlayer = $data[$firstKey];
                    $result['sample_player_data'] = $limitOutput ? $this->limitArrayDepth($firstPlayer, 2) : $firstPlayer;

                    // Check if this looks like ADP data
                    $result['data_structure_analysis'] = $this->analyzeAdpDataStructure($firstPlayer);
                }

                // Check for known top players
                $topPlayerIds = ['4046', '4034', '4881', '4037', '4039']; // CMC, Josh Allen, Tyreek, Ekeler, Kupp
                $foundPlayers = [];
                foreach ($topPlayerIds as $playerId) {
                    if (isset($data[$playerId])) {
                        $foundPlayers[$playerId] = $data[$playerId];
                    }
                }
                $result['known_top_players_found'] = $foundPlayers;
            } else {
                $result['raw_response'] = $response->body();
            }

            return $result;
        } catch (\Exception $e) {
            return [
                'endpoint' => "/v1/stats/nfl/{$season}/adp",
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
            ];
        }
    }

    private function inspectSleeperProjections(string $season, int $week): array
    {
        /** @var SleeperConnector $connector */
        $connector = LaravelApp::make(SleeperConnector::class);

        try {
            $response = $connector->send(new GetWeeklyProjections('nfl', $season, $week));
            $data = $response->json();

            $result = [
                'endpoint' => "/v1/projections/nfl/{$season}/{$week}",
                'status' => $response->status(),
                'success' => $response->successful(),
                'data_type' => gettype($data),
                'data_size' => is_array($data) ? count($data) : strlen($data ?? ''),
            ];

            if ($result['success'] && is_array($data)) {
                $sampleKeys = array_slice(array_keys($data), 0, 3);
                $result['sample_keys'] = $sampleKeys;

                if (!empty($data)) {
                    $firstKey = $sampleKeys[0];
                    $firstPlayer = $data[$firstKey];
                    $result['sample_player_data'] = $limitOutput ? $this->limitArrayDepth($firstPlayer, 2) : $firstPlayer;

                    // Check if this looks like projections data
                    $result['data_structure_analysis'] = $this->analyzeProjectionsDataStructure($firstPlayer);
                }

                // Check for known top players
                $topPlayerIds = ['4046', '4034', '4881', '4037', '4039'];
                $foundPlayers = [];
                foreach ($topPlayerIds as $playerId) {
                    if (isset($data[$playerId])) {
                        $foundPlayers[$playerId] = $limitOutput ? $this->limitArrayDepth($data[$playerId], 2) : $data[$playerId];
                    }
                }
                $result['known_top_players_found'] = $foundPlayers;
            } else {
                $result['raw_response'] = $response->body();
            }

            return $result;
        } catch (\Exception $e) {
            return [
                'endpoint' => "/v1/projections/nfl/{$season}/{$week}",
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
            ];
        }
    }

    private function inspectEspnFantasy(int $season): array
    {
        /** @var EspnFantasyConnector $connector */
        $connector = LaravelApp::make(EspnFantasyConnector::class);

        try {
            $response = $connector->send(new GetFantasyPlayers($season, 'mDraftDetail', 300));
            $data = $response->json();

            $result = [
                'endpoint' => "/apis/v3/games/ffl/seasons/{$season}/players?view=mDraftDetail&limit=300",
                'status' => $response->status(),
                'success' => $response->successful(),
                'data_type' => gettype($data),
                'data_size' => is_array($data) ? count($data) : strlen($data ?? ''),
            ];

            if ($result['success'] && is_array($data)) {
                $result['sample_players'] = array_slice($data, 0, 3);

                // Check for known top players by name
                $topPlayerNames = ['Josh Allen', 'Christian McCaffrey', 'Tyreek Hill'];
                $foundPlayers = [];
                foreach ($data as $player) {
                    $fullName = $player['fullName'] ?? '';
                    if (in_array($fullName, $topPlayerNames)) {
                        $foundPlayers[] = $limitOutput ? $this->limitArrayDepth($player, 2) : $player;
                    }
                }
                $result['known_top_players_found'] = $foundPlayers;

                // Analyze data structure
                if (!empty($data)) {
                    $result['data_structure_analysis'] = $this->analyzeEspnDataStructure($data[0]);
                }
            } else {
                $result['raw_response'] = $response->body();
            }

            return $result;
        } catch (\Exception $e) {
            return [
                'endpoint' => "/apis/v3/games/ffl/seasons/{$season}/players?view=mDraftDetail&limit=300",
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
            ];
        }
    }

    private function analyzeAdpDataStructure(array $playerData): array
    {
        $analysis = [
            'has_rankings' => false,
            'ranking_fields_found' => [],
            'expected_fields' => ['rank_ppr', 'rank_half_ppr', 'rank_std'],
        ];

        foreach ($analysis['expected_fields'] as $field) {
            if (isset($playerData[$field])) {
                $analysis['has_rankings'] = true;
                $analysis['ranking_fields_found'][] = $field;
            }
        }

        $analysis['all_fields'] = array_keys($playerData);

        return $analysis;
    }

    private function analyzeProjectionsDataStructure(array $playerData): array
    {
        $analysis = [
            'has_projections' => false,
            'projection_fields_found' => [],
            'expected_fields' => ['pts_ppr', 'pts_half_ppr', 'pts_std'],
        ];

        foreach ($analysis['expected_fields'] as $field) {
            if (isset($playerData[$field])) {
                $analysis['has_projections'] = true;
                $analysis['projection_fields_found'][] = $field;
            }
        }

        $analysis['all_fields'] = array_keys($playerData);

        return $analysis;
    }

    private function analyzeEspnDataStructure(array $playerData): array
    {
        $analysis = [
            'has_adp' => false,
            'has_rankings' => false,
            'adp_fields_found' => [],
            'ranking_fields_found' => [],
        ];

        // Check for ADP fields
        $adpFields = ['averageDraftPosition'];
        foreach ($adpFields as $field) {
            if (isset($playerData[$field])) {
                $analysis['has_adp'] = true;
                $analysis['adp_fields_found'][] = $field;
            }
        }

        // Check for ranking fields
        if (isset($playerData['draftRanksByRankType'])) {
            $analysis['has_rankings'] = true;
            $analysis['ranking_fields_found'] = array_keys($playerData['draftRanksByRankType']);
        }

        $analysis['all_fields'] = array_keys($playerData);

        return $analysis;
    }

    private function limitArrayDepth(array $array, int $maxDepth, int $currentDepth = 0): array
    {
        if ($currentDepth >= $maxDepth) {
            return ['...'];
        }

        $result = [];
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $result[$key] = $this->limitArrayDepth($value, $maxDepth, $currentDepth + 1);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
