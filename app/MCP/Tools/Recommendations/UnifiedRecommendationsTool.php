<?php

namespace App\MCP\Tools\Recommendations;

use App\Services\SleeperSdk;
use App\Services\EspnSdk;
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
                    'description' => 'Type of recommendation to generate',
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
                'blend_adp' => ['type' => 'boolean', 'default' => true, 'description' => 'Blend Sleeper and ESPN ADP'],
                'espn_view' => ['type' => 'string', 'default' => 'mDraftDetail', 'description' => 'ESPN view for ADP blending'],

                // Trade-specific parameters
                'offer' => [
                    'type' => 'object',
                    'description' => 'Trade mode only',
                    'properties' => [
                        'from_roster_id' => ['type' => 'integer'],
                        'to_roster_id' => ['type' => 'integer'],
                        'sending' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'receiving' => ['type' => 'array', 'items' => ['type' => 'string']],
                    ],
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
        if (! isset($arguments['league_id']) || ! isset($arguments['roster_id'])) {
            throw new \InvalidArgumentException('Missing required parameters: league_id and roster_id');
        }

        /** @var SleeperSdk $sdk */
        $sdk = LaravelApp::make(SleeperSdk::class);
        /** @var EspnSdk $espn */
        $espn = LaravelApp::make(EspnSdk::class);
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

        $myRoster = collect($rosters)->firstWhere('sleeper_roster_id', (string) $rosterId) ?? [];
        $currentPlayers = array_map('strval', (array) ($myRoster['players'] ?? []));
        $needCounts = ['QB' => 0, 'RB' => 0, 'WR' => 0, 'TE' => 0];
        $myTeamsByPos = ['QB' => [], 'RB' => [], 'WR' => [], 'TE' => []];
        $myByesByPos = ['QB' => [], 'RB' => [], 'WR' => [], 'TE' => []];
        foreach ($currentPlayers as $pid) {
            $pos = strtoupper((string) (($catalog[$pid]['position'] ?? '') ?: ''));
            if (isset($needCounts[$pos])) {
                $needCounts[$pos]++;
            }
            $team = strtoupper((string) ($catalog[$pid]['team'] ?? ''));
            if (isset($myTeamsByPos[$pos]) && $team !== '') {
                $myTeamsByPos[$pos][$team] = true;
            }
            $bye = $catalog[$pid]['bye_week'] ?? ($catalog[$pid]['bye'] ?? null);
            if (isset($myByesByPos[$pos]) && is_numeric($bye)) {
                $myByesByPos[$pos][(int) $bye] = true;
            }
        }

        $rosterPositions = array_values(array_filter((array) ($league['roster_positions'] ?? []), fn ($p) => ! in_array(strtoupper((string) $p), ['BN', 'IR', 'TAXI'], true)));
        $posCounts = ['QB' => 0, 'RB' => 0, 'WR' => 0, 'TE' => 0];
        foreach ($rosterPositions as $slot) {
            $slot = strtoupper((string) $slot);
            if (in_array($slot, ['QB', 'RB', 'WR', 'TE'], true)) {
                $posCounts[$slot] = ($posCounts[$slot] ?? 0) + 1;
            } elseif (in_array($slot, ['FLEX', 'WR_RB', 'WR_TE', 'RB_TE', 'REC_FLEX'], true)) {
                $posCounts['RB'] += 0.34;
                $posCounts['WR'] += 0.34;
                $posCounts['TE'] += 0.32;
            } elseif (in_array($slot, ['SUPER_FLEX', 'SUPERFLEX', 'SFLEX'], true)) {
                $posCounts['QB'] += 0.5;
                $posCounts['RB'] += 0.2;
                $posCounts['WR'] += 0.2;
                $posCounts['TE'] += 0.1;
            }
        }

        $replacement = [];
        foreach (['QB', 'RB', 'WR', 'TE'] as $pos) {
            $pool = [];
            foreach ($catalog as $pidX => $metaX) {
                $p = strtoupper((string) ($metaX['position'] ?? ''));
                if ($p !== $pos) {
                    continue;
                }
                $pidX = (string) ($metaX['player_id'] ?? $pidX);
                $pool[] = (float) (($projections[$pidX]['pts_half_ppr'] ?? $projections[$pidX]['pts_ppr'] ?? $projections[$pidX]['pts_std'] ?? 0));
            }
            rsort($pool);
            $index = max(0, (int) round(($posCounts[$pos] ?? 0) * 12));
            $replacement[$pos] = $pool[$index] ?? 0.0;
        }

        $round = (int) ($arguments['round'] ?? 1);
        $pickInRound = (int) ($arguments['pick_in_round'] ?? 1);
        $roundSize = (int) ($arguments['round_size'] ?? 12);
        $snake = (bool) ($arguments['snake'] ?? true);
        $currentPickOverall = ($round - 1) * $roundSize + $pickInRound;
        $picksUntilNext = $snake
            ? ($round % 2 === 1 ? (2 * $roundSize - $pickInRound * 2 + 1) : (2 * $pickInRound - 1))
            : $roundSize;

        $adpIndex = [];
        foreach ($adp as $row) {
            $adpIndex[(string) ($row['player_id'] ?? '')] = (float) ($row['adp'] ?? 999.0);
        }

        $mcRuns = 500;
        $posRunRisk = ['QB' => 0.0, 'RB' => 0.0, 'WR' => 0.0, 'TE' => 0.0];
        foreach (array_keys($posRunRisk) as $posKey) {
            $posPool = [];
            foreach ($catalog as $pidX => $metaX) {
                $p = strtoupper((string) ($metaX['position'] ?? ''));
                if ($p !== $posKey) {
                    continue;
                }
                $pidX = (string) ($metaX['player_id'] ?? $pidX);
                $posPool[] = [
                    'player_id' => $pidX,
                    'adp' => $adpIndex[$pidX] ?? 999.0,
                ];
            }
            if (empty($posPool)) {
                continue;
            }
            $exhausted = 0;
            for ($i = 0; $i < $mcRuns; $i++) {
                $countGone = 0;
                foreach ($posPool as $rowX) {
                    $d = ($rowX['adp'] - $currentPickOverall);
                    $pGone = 1.0 / (1.0 + exp(0.12 * ($d - $picksUntilNext)));
                    if (mt_rand() / mt_getrandmax() < $pGone) {
                        $countGone++;
                    }
                }
                if ($countGone > max(1, (int) round(($posCounts[$posKey] ?? 0) * 0.8))) {
                    $exhausted++;
                }
            }
            $posRunRisk[$posKey] = $exhausted / $mcRuns;
        }

        $blendAdp = (bool) ($arguments['blend_adp'] ?? true);
        if ($blendAdp) {
            $espnView = (string) ($arguments['espn_view'] ?? 'mDraftDetail');
            $espnPlayers = $espn->getFantasyPlayers((int) $season, $espnView, 2000);
            $nameToPid = [];
            $espnIdToPid = [];
            foreach ($catalog as $pidKey => $meta) {
                $pid = (string) ($meta['player_id'] ?? $pidKey);
                $fullName = (string) ($meta['full_name'] ?? trim(($meta['first_name'] ?? '').' '.($meta['last_name'] ?? '')));
                $nameToPid[self::normalizeName($fullName)] = $pid;
                if (isset($meta['espn_id']) && $meta['espn_id'] !== null && $meta['espn_id'] !== '') {
                    $espnIdToPid[(string) $meta['espn_id']] = $pid;
                }
            }
            foreach ($espnPlayers as $item) {
                $adpCandidate = null;
                if (isset($item['averageDraftPosition']) && is_numeric($item['averageDraftPosition'])) {
                    $adpCandidate = (float) $item['averageDraftPosition'];
                } elseif (isset($item['draftRanksByRankType']) && is_array($item['draftRanksByRankType'])) {
                    $rankType = $item['draftRanksByRankType']['STANDARD'] ?? ($item['draftRanksByRankType']['PPR'] ?? null);
                    if (is_array($rankType) && isset($rankType['rank']) && is_numeric($rankType['rank'])) {
                        $adpCandidate = (float) $rankType['rank'];
                    }
                }
                if ($adpCandidate === null) {
                    continue;
                }
                $pid = null;
                $espnId = null;
                if (isset($item['id']) && is_numeric($item['id'])) {
                    $espnId = (string) $item['id'];
                } elseif (isset($item['player']['id']) && is_numeric($item['player']['id'])) {
                    $espnId = (string) $item['player']['id'];
                } elseif (isset($item['playerId']) && is_numeric($item['playerId'])) {
                    $espnId = (string) $item['playerId'];
                }
                if ($espnId !== null && isset($espnIdToPid[$espnId])) {
                    $pid = $espnIdToPid[$espnId];
                } else {
                    $espnName = (string) ($item['fullName'] ?? ($item['player']['fullName'] ?? (($item['firstName'] ?? '').' '.($item['lastName'] ?? ''))));
                    $norm = self::normalizeName($espnName);
                    $pid = $nameToPid[$norm] ?? null;
                }
                if ($pid === null) {
                    continue;
                }
                if (isset($adpIndex[$pid])) {
                    $adpIndex[$pid] = ($adpIndex[$pid] + $adpCandidate) / 2.0;
                } else {
                    $adpIndex[$pid] = $adpCandidate;
                }
            }
        }

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
            $needWeight = match ($pos) {
                'RB' => max(0, 3 - ($needCounts['RB'] ?? 0)),
                'WR' => max(0, 4 - ($needCounts['WR'] ?? 0)),
                'QB' => max(0, 1 - ($needCounts['QB'] ?? 0)),
                'TE' => max(0, 1 - ($needCounts['TE'] ?? 0)),
                default => 0,
            };
            $vorp = max(0.0, $proj - ($replacement[$pos] ?? 0.0));
            $adpLeverage = ($adpVal - $currentPickOverall) / max(1.0, $roundSize);
            $willBeGone = $adpVal < ($currentPickOverall + $picksUntilNext - 2) ? 1.0 : 0.0;
            $oppCost = ($willBeGone ? max(0.0, $vorp) : 0.0) * (1.0 + 0.5 * ($posRunRisk[$pos] ?? 0.0));
            $construction = match ($pos) {
                'RB' => max(0.0, 3 - ($needCounts['RB'] ?? 0)),
                'WR' => max(0.0, 4 - ($needCounts['WR'] ?? 0)),
                'QB' => max(0.0, 1 - ($needCounts['QB'] ?? 0)),
                'TE' => max(0.0, 1 - ($needCounts['TE'] ?? 0)),
                default => 0.0,
            };
            $strategy = Cache::get('mcp:strategy', []);
            $byePenalty = 0.0;
            $bye = $meta['bye_week'] ?? ($meta['bye'] ?? null);
            if (is_numeric($bye) && isset($myByesByPos[$pos][(int) $bye])) {
                $byePenalty = 1.0;
            }
            $injuryPenalty = 0.0;
            $injury = strtolower((string) ($meta['injury_status'] ?? ($meta['status'] ?? '')));
            if ($injury !== '') {
                $injuryPenalty = match ($injury) {
                    'out' => 1.0,
                    'doubtful' => 0.8,
                    'suspended' => 0.7,
                    'questionable' => 0.5,
                    default => 0.2,
                };
            }
            $team = strtoupper((string) ($meta['team'] ?? ''));
            $stackBonus = 0.0;
            if ($team !== '') {
                if ($pos === 'QB' && (isset($myTeamsByPos['WR'][$team]) || isset($myTeamsByPos['TE'][$team]))) {
                    $stackBonus = 1.0;
                } elseif (in_array($pos, ['WR', 'TE'], true) && isset($myTeamsByPos['QB'][$team])) {
                    $stackBonus = 1.0;
                }
            }
            $wV = 1.5;
            $wOpp = 1.0;
            $wADP = max(0.2, 1.0 - 0.05 * ($round - 1));
            $wCons = 0.7;
            $wBye = 0.1;
            $wInj = 0.5;
            $wStack = 0.3;
            $risk = strtolower((string) ($strategy['risk'] ?? ''));
            if ($risk === 'high') {
                $wV += 0.3;
                $wADP *= 0.8;
            }
            if ($risk === 'low') {
                $wV -= 0.2;
                $wADP *= 1.2;
            }
            if (! empty($strategy['stack_qb'])) {
                $wStack = 0.6;
            }
            if (! empty($strategy['hero_rb']) && $pos === 'RB' && $round <= 4) {
                $construction += 0.5;
            }
            if (! empty($strategy['zero_rb']) && $pos === 'RB' && $round <= 6) {
                $construction = max(0.0, $construction - 0.7);
            }

            $score = $wV * $vorp + $wOpp * $oppCost + $wCons * $construction + $wADP * $adpLeverage - $wBye * $byePenalty - $wInj * $injuryPenalty + $wStack * $stackBonus;
            $candidates[] = [
                'player_id' => $pid,
                'name' => $meta['full_name'] ?? trim(($meta['first_name'] ?? '').' '.($meta['last_name'] ?? '')),
                'position' => $pos,
                'team' => $meta['team'] ?? null,
                'adp' => $adpVal,
                'projected_points' => $proj,
                'vorp' => $vorp,
                'score' => $score,
                'need_weight' => $needWeight,
                'adp_leverage' => $adpLeverage,
                'opp_cost' => $oppCost,
                'construction' => $construction,
                'bye_penalty' => $byePenalty,
                'injury_penalty' => $injuryPenalty,
                'stack_bonus' => $stackBonus,
            ];
        }

        usort($candidates, fn ($a, $b) => $b['score'] <=> $a['score']);
        $top = array_slice($candidates, 0, $limit);

        return [
            'mode' => 'draft',
            'recommendations' => $top,
            'pos_run_risk' => $posRunRisk,
            'meta' => [
                'league_id' => $leagueId,
                'roster_id' => $rosterId,
                'season' => $season,
                'week' => $week,
                'current_pick_overall' => $currentPickOverall,
                'picks_until_next' => $picksUntilNext,
                'round' => $round,
                'pick_in_round' => $pickInRound,
                'round_size' => $roundSize,
                'snake' => $snake,
                'blend_adp' => $blendAdp,
            ],
        ];
    }

    private function getWaiverRecommendations(array $arguments): array
    {
        // Validate required parameters
        if (! isset($arguments['league_id']) || ! isset($arguments['roster_id'])) {
            throw new \InvalidArgumentException('Missing required parameters: league_id and roster_id');
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
                'name' => $meta['full_name'] ?? trim(($meta['first_name'] ?? '') . ' ' . ($meta['last_name'] ?? '')),
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
            'week' => $week,
        ];
    }

    private function getTradeAnalysis(array $arguments): array
    {
        // Validate required parameters
        if (! isset($arguments['league_id']) || ! isset($arguments['offer'])) {
            throw new \InvalidArgumentException('Missing required parameters: league_id and offer');
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

        $sending = [];
        foreach ($offer['sending'] as $pid) {
            $pid = (string) $pid;
            $proj = (float) (($projections[$pid]['pts_half_ppr'] ?? $projections[$pid]['pts_ppr'] ?? $projections[$pid]['pts_std'] ?? 0));
            $adpVal = $adpIndex[$pid] ?? 999.0;
            $sendingValue += ($proj * 0.7) + (1000 - $adpVal) * 0.3; // Weighted combination
            $meta = $catalog[$pid] ?? [];
            $sending[] = [
                'player_id' => $pid,
                'name' => $meta['full_name'] ?? trim(($meta['first_name'] ?? '') . ' ' . ($meta['last_name'] ?? '')),
                'position' => $meta['position'] ?? null,
                'team' => $meta['team'] ?? null,
            ];
        }

        $receiving = [];
        foreach ($offer['receiving'] as $pid) {
            $pid = (string) $pid;
            $proj = (float) (($projections[$pid]['pts_half_ppr'] ?? $projections[$pid]['pts_ppr'] ?? $projections[$pid]['pts_std'] ?? 0));
            $adpVal = $adpIndex[$pid] ?? 999.0;
            $receivingValue += ($proj * 0.7) + (1000 - $adpVal) * 0.3;
            $meta = $catalog[$pid] ?? [];
            $receiving[] = [
                'player_id' => $pid,
                'name' => $meta['full_name'] ?? trim(($meta['first_name'] ?? '') . ' ' . ($meta['last_name'] ?? '')),
                'position' => $meta['position'] ?? null,
                'team' => $meta['team'] ?? null,
            ];
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
                'sending_players' => $sending,
                'receiving_players' => $receiving,
            ],
            'league_id' => $leagueId,
            'season' => $season,
            'week' => $week,
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
                'te_opportunities' => 'TE premium increases in playoff weeks due to higher scoring',
            ],
            'strategy_tips' => [
                'matchup_research' => 'Prioritize players with favorable playoff schedules',
                'injury_monitoring' => 'Pay extra attention to injury reports in playoff weeks',
                'situational_usage' => 'Consider players with higher snap counts in playoffs',
            ],
        ];

        return [
            'mode' => 'playoffs',
            'planning' => $planning,
            'season' => $season,
            'weeks' => $weeks,
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
