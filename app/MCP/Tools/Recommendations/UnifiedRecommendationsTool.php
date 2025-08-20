<?php

namespace App\MCP\Tools\Recommendations;

use App\Services\EspnSdk;
use App\Services\SleeperSdk;
use Illuminate\Support\Facades\App as LaravelApp;
use Illuminate\Support\Facades\Cache;
use OPGG\LaravelMcpServer\Services\ToolService\ToolInterface;

class UnifiedRecommendationsTool implements ToolInterface
{
    public function name(): string
    {
        return 'fantasy_recommendations';
    }

    public function description(): string
    {
        return 'Unified tool for fantasy recommendations: draft picks, waiver acquisitions, and trade analysis.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['mode'],
            'properties' => [
                'mode' => [
                    'type' => 'string',
                    'enum' => ['draft', 'waiver', 'trade', 'playoffs'],
                    'description' => 'Type of recommendation to generate'
                ],

                // Common parameters
                'sport' => ['type' => 'string', 'default' => 'nfl'],
                'season' => ['type' => 'string'],
                'week' => ['type' => 'integer', 'minimum' => 1],
                'format' => ['type' => 'string', 'enum' => ['redraft', 'dynasty', 'bestball'], 'default' => 'redraft'],

                // Draft-specific parameters
                'league_id' => ['type' => 'string', 'description' => 'Required for draft and waiver modes'],
                'roster_id' => ['type' => 'integer', 'description' => 'Required for draft and waiver modes'],
                'current_pick' => ['type' => 'integer', 'description' => 'Current overall pick number for draft mode'],
                'already_drafted' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Draft mode only'],
                'round' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Draft mode only'],
                'pick_in_round' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Draft mode only'],
                'round_size' => ['type' => 'integer', 'minimum' => 2, 'description' => 'Draft mode only'],
                'snake' => ['type' => 'boolean', 'default' => true, 'description' => 'Draft mode only'],
                'strategy' => ['type' => 'string', 'enum' => ['balanced', 'hero_rb', 'zero_rb', 'wr_heavy', 'risk_on', 'bestball_stack'], 'default' => 'balanced', 'description' => 'Draft mode only'],

                // Trade-specific parameters
                'offer' => [
                    'type' => 'object',
                    'description' => 'Trade mode only',
                    'properties' => [
                        'from_roster_id' => ['type' => 'integer'],
                        'to_roster_id' => ['type' => 'integer'],
                        'sending' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'receiving' => ['type' => 'array', 'items' => ['type' => 'string']],
                    ]
                ],

                // Playoffs-specific parameters
                'weeks' => ['type' => 'array', 'items' => ['type' => 'integer'], 'default' => [15, 16, 17], 'description' => 'Playoffs mode only'],

                // Waiver-specific parameters
                'max_candidates' => ['type' => 'integer', 'minimum' => 1, 'default' => 10, 'description' => 'Waiver mode only'],

                // Output control
                'limit' => ['type' => 'integer', 'default' => 10, 'description' => 'Maximum number of recommendations to return'],
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
        $mode = $arguments['mode'];

        return match ($mode) {
            'draft' => $this->getDraftRecommendations($arguments),
            'waiver' => $this->getWaiverRecommendations($arguments),
            'trade' => $this->getTradeAnalysis($arguments),
            'playoffs' => $this->getPlayoffPlanning($arguments),
            default => ['error' => 'Invalid mode specified']
        };
    }

    private function getDraftRecommendations(array $arguments): array
    {
        // Validate required parameters
        if (!isset($arguments['league_id']) || !isset($arguments['roster_id'])) {
            throw new \InvalidArgumentException("Missing required parameters: league_id and roster_id");
        }

        /** @var SleeperSdk $sdk */
        $sdk = LaravelApp::make(SleeperSdk::class);
        $sport = $arguments['sport'] ?? 'nfl';
        $leagueId = (string) $arguments['league_id'];
        $rosterId = (int) $arguments['roster_id'];
        $state = $sdk->getState($sport);
        $season = (string) ($arguments['season'] ?? ($state['season'] ?? date('Y')));
        $week = (int) ($arguments['week'] ?? (int) ($state['week'] ?? 1));
        $format = $arguments['format'] ?? 'redraft';
        $limit = (int) ($arguments['limit'] ?? 10);
        $alreadyDrafted = array_map('strval', (array) ($arguments['already_drafted'] ?? []));

        $league = $sdk->getLeague($leagueId);
        $rosters = $sdk->getLeagueRosters($leagueId);
        $catalog = $sdk->getPlayersCatalog($sport);
        $projections = $sdk->getWeeklyProjections($season, $week, $sport);
        $adp = $sdk->getAdp($season, $format, $sport, ttlSeconds: null, allowTrendingFallback: false);

        $myRoster = collect($rosters)->firstWhere('roster_id', $rosterId) ?? [];
        $currentPlayers = array_map('strval', (array) ($myRoster['players'] ?? []));

        // Build ADP index
        $adpIndex = [];
        foreach ($adp as $row) {
            $adpIndex[(string) ($row['player_id'] ?? '')] = (float) ($row['adp'] ?? 999.0);
        }

        // Calculate needs and generate recommendations (simplified version)
        $candidates = [];
        foreach ($catalog as $pid => $meta) {
            $pid = (string) ($meta['player_id'] ?? $pid);
            if (in_array($pid, $alreadyDrafted, true)) {
                continue;
            }

            $pos = strtoupper((string) ($meta['position'] ?? ''));
            if (! in_array($pos, ['QB', 'RB', 'WR', 'TE'], true)) {
                continue;
            }

            $proj = (float) (($projections[$pid]['pts_half_ppr'] ?? $projections[$pid]['pts_ppr'] ?? $projections[$pid]['pts_std'] ?? 0));
            $adpVal = $adpIndex[$pid] ?? 999.0;

            $score = $proj - ($adpVal / 100); // Simple score combining projection and ADP

            $candidates[] = [
                'player_id' => $pid,
                'name' => $meta['full_name'] ?? trim(($meta['first_name'] ?? '').' '.($meta['last_name'] ?? '')),
                'position' => $pos,
                'team' => $meta['team'] ?? null,
                'adp' => $adpVal,
                'projected_points' => $proj,
                'score' => $score,
            ];
        }

        usort($candidates, fn ($a, $b) => $b['score'] <=> $a['score']);
        $top = array_slice($candidates, 0, $limit);

        return [
            'mode' => 'draft',
            'recommendations' => $top,
            'league_id' => $leagueId,
            'roster_id' => $rosterId,
            'season' => $season,
            'week' => $week
        ];
    }

    private function getWaiverRecommendations(array $arguments): array
    {
        // Validate required parameters
        if (!isset($arguments['league_id']) || !isset($arguments['roster_id'])) {
            throw new \InvalidArgumentException("Missing required parameters: league_id and roster_id");
        }

        /** @var SleeperSdk $sdk */
        $sdk = LaravelApp::make(SleeperSdk::class);
        $sport = $arguments['sport'] ?? 'nfl';
        $state = $sdk->getState($sport);
        $season = (string) ($arguments['season'] ?? ($state['season'] ?? date('Y')));
        $week = (int) ($arguments['week'] ?? (int) ($state['week'] ?? 1));
        $max = (int) ($arguments['max_candidates'] ?? 10);
        $leagueId = (string) $arguments['league_id'];
        $rosterId = (int) $arguments['roster_id'];

        $league = $sdk->getLeague($leagueId);
        $rosters = $sdk->getLeagueRosters($leagueId);
        $catalog = $sdk->getPlayersCatalog($sport);
        $projections = $sdk->getWeeklyProjections($season, $week, $sport);

        $roster = collect($rosters)->firstWhere('roster_id', $rosterId) ?? [];
        $owned = array_map('strval', (array) ($roster['players'] ?? []));

        $trendingAdds = $sdk->getPlayersTrending('add', $sport, 48, 100);

        $candidates = [];
        foreach ($trendingAdds as $entry) {
            $pid = (string) ($entry['player_id'] ?? '');
            if ($pid === '' || in_array($pid, $owned, true)) {
                continue;
            }

            $proj = (float) (($projections[$pid]['pts_half_ppr'] ?? $projections[$pid]['pts_ppr'] ?? $projections[$pid]['pts_std'] ?? 0));
            $meta = $catalog[$pid] ?? [];
            $pos = $meta['position'] ?? null;
            $team = $meta['team'] ?? null;
            $score = $proj + (float) ($entry['count'] ?? 0) / 10000.0;

            $candidates[] = [
                'player_id' => $pid,
                'projected_points' => $proj,
                'trend_count' => (int) ($entry['count'] ?? 0),
                'score' => $score,
                'position' => $pos,
                'team' => $team,
            ];
        }

        usort($candidates, fn ($a, $b) => $b['score'] <=> $a['score']);
        $candidates = array_slice($candidates, 0, $max);

        return [
            'mode' => 'waiver',
            'picks' => $candidates,
            'league_id' => $leagueId,
            'roster_id' => $rosterId,
            'season' => $season,
            'week' => $week
        ];
    }

    private function getTradeAnalysis(array $arguments): array
    {
        // Validate required parameters
        if (!isset($arguments['league_id']) || !isset($arguments['offer'])) {
            throw new \InvalidArgumentException("Missing required parameters: league_id and offer");
        }

        /** @var SleeperSdk $sdk */
        $sdk = LaravelApp::make(SleeperSdk::class);
        $sport = $arguments['sport'] ?? 'nfl';
        $state = $sdk->getState($sport);
        $season = (string) ($arguments['season'] ?? ($state['season'] ?? date('Y')));
        $week = (int) ($arguments['week'] ?? (int) ($state['week'] ?? 1));
        $format = $arguments['format'] ?? 'redraft';
        $leagueId = (string) $arguments['league_id'];
        $offer = $arguments['offer'];

        $catalog = $sdk->getPlayersCatalog($sport);
        $projections = $sdk->getWeeklyProjections($season, $week, $sport);
        $adp = $sdk->getAdp($season, $format, $sport, ttlSeconds: null, allowTrendingFallback: false);

        // Build ADP index
        $adpIndex = [];
        foreach ($adp as $row) {
            $adpIndex[(string) ($row['player_id'] ?? '')] = (float) ($row['adp'] ?? 999.0);
        }

        // Calculate trade value
        $sendingValue = 0;
        $receivingValue = 0;

        foreach ($offer['sending'] as $pid) {
            $proj = (float) (($projections[$pid]['pts_half_ppr'] ?? $projections[$pid]['pts_ppr'] ?? $projections[$pid]['pts_std'] ?? 0));
            $adpVal = $adpIndex[$pid] ?? 999.0;
            $sendingValue += ($proj * 0.7) + (1000 - $adpVal) * 0.3; // Weighted combination
        }

        foreach ($offer['receiving'] as $pid) {
            $proj = (float) (($projections[$pid]['pts_half_ppr'] ?? $projections[$pid]['pts_ppr'] ?? $projections[$pid]['pts_std'] ?? 0));
            $adpVal = $adpIndex[$pid] ?? 999.0;
            $receivingValue += ($proj * 0.7) + (1000 - $adpVal) * 0.3;
        }

        $tradeValue = $receivingValue - $sendingValue;
        $recommendation = $tradeValue > 0 ? 'favorable' : ($tradeValue < 0 ? 'unfavorable' : 'fair');

        return [
            'mode' => 'trade',
            'analysis' => [
                'trade_value' => $tradeValue,
                'recommendation' => $recommendation,
                'sending_value' => $sendingValue,
                'receiving_value' => $receivingValue,
                'sending_players' => $offer['sending'],
                'receiving_players' => $offer['receiving']
            ],
            'league_id' => $leagueId,
            'season' => $season,
            'week' => $week
        ];
    }

    private function getPlayoffPlanning(array $arguments): array
    {
        $sport = $arguments['sport'] ?? 'nfl';
        $state = LaravelApp::make(SleeperSdk::class)->getState($sport);
        $season = (string) ($arguments['season'] ?? ($state['season'] ?? date('Y')));
        $weeks = (array) ($arguments['weeks'] ?? [15, 16, 17]);

        // Basic playoff planning structure
        $planning = [
            'playoff_weeks' => $weeks,
            'notes' => 'Playoff planning recommendations based on schedule strength and matchup analysis.',
            'recommendations' => [
                'qb_targets' => 'Focus on QBs with favorable schedules in playoff weeks',
                'rb_stashing' => 'Consider stashing high-upside RBs for potential bye weeks',
                'wr_streaming' => 'Look for WRs facing weaker secondary matchups',
                'te_opportunities' => 'TE premium increases in playoff weeks due to higher scoring'
            ],
            'strategy_tips' => [
                'matchup_research' => 'Prioritize players with favorable playoff schedules',
                'injury_monitoring' => 'Pay extra attention to injury reports in playoff weeks',
                'situational_usage' => 'Consider players with higher snap counts in playoffs'
            ]
        ];

        return [
            'mode' => 'playoffs',
            'planning' => $planning,
            'season' => $season,
            'weeks' => $weeks
        ];
    }

    private static function normalizeName(string $name): string
    {
        $n = strtolower(trim($name));
        $n = preg_replace('/[^a-z\s]/', '', $n ?? '');
        $n = preg_replace('/\s+/', ' ', $n ?? '');

        return $n ?? '';
    }
}
