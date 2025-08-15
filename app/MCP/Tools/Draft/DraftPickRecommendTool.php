<?php

namespace App\MCP\Tools\Draft;

use App\Services\EspnSdk;
use App\Services\SleeperSdk;
use Illuminate\Support\Facades\App as LaravelApp;
use Illuminate\Support\Facades\Cache;
use OPGG\LaravelMcpServer\Services\ToolService\ToolInterface;

class DraftPickRecommendTool implements ToolInterface
{
    public function name(): string
    {
        return 'draft_pick_recommend';
    }

    public function description(): string
    {
        return 'Recommend best available picks given current pick, roster needs, and board.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['league_id', 'roster_id'],
            'properties' => [
                'league_id' => ['type' => 'string'],
                'roster_id' => ['type' => 'integer'],
                'season' => ['type' => 'string'],
                'week' => ['type' => 'integer', 'minimum' => 1],
                'sport' => ['type' => 'string', 'default' => 'nfl'],
                'format' => ['type' => 'string', 'enum' => ['redraft', 'dynasty', 'bestball'], 'default' => 'redraft'],
                'limit' => ['type' => 'integer', 'default' => 10],
                'already_drafted' => ['type' => 'array', 'items' => ['type' => 'string']],
                'blend_adp' => ['type' => 'boolean', 'default' => true],
                'espn_view' => ['type' => 'string', 'default' => 'mDraftDetail'],
                // Round/pick context for pick-horizon/opportunity cost
                'round' => ['type' => 'integer', 'minimum' => 1],
                'pick_in_round' => ['type' => 'integer', 'minimum' => 1],
                'round_size' => ['type' => 'integer', 'minimum' => 2],
                'snake' => ['type' => 'boolean', 'default' => true],
                // Strategy profile stub
                'strategy' => ['type' => 'string', 'enum' => ['balanced', 'hero_rb', 'zero_rb', 'wr_heavy', 'risk_on', 'bestball_stack'], 'default' => 'balanced'],
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
        $leagueId = (string) $arguments['league_id'];
        $rosterId = (int) $arguments['roster_id'];
        $state = $sdk->getState($sport);
        $season = (string) ($arguments['season'] ?? ($state['season'] ?? date('Y')));
        $week = (int) ($arguments['week'] ?? (int) ($state['week'] ?? 1));
        $format = $arguments['format'] ?? 'redraft';
        $limit = (int) ($arguments['limit'] ?? 10);
        $alreadyDrafted = array_map('strval', (array) ($arguments['already_drafted'] ?? []));

        /** @var EspnSdk $espn */
        $espn = LaravelApp::make(EspnSdk::class);
        $league = $sdk->getLeague($leagueId);
        $rosters = $sdk->getLeagueRosters($leagueId);
        $catalog = $sdk->getPlayersCatalog($sport);
        $projections = $sdk->getWeeklyProjections($season, $week, $sport);
        $adp = $sdk->getAdp($season, $format, $sport);

        $myRoster = collect($rosters)->firstWhere('roster_id', $rosterId) ?? [];
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

        // Replacement level baselines per position using league roster settings
        $rosterPositions = array_values(array_filter((array) ($league['roster_positions'] ?? []), fn ($p) => ! in_array(strtoupper((string) $p), ['BN', 'IR', 'TAXI'], true)));
        $posCounts = ['QB' => 0, 'RB' => 0, 'WR' => 0, 'TE' => 0];
        foreach ($rosterPositions as $slot) {
            $slot = strtoupper((string) $slot);
            if (in_array($slot, ['QB', 'RB', 'WR', 'TE'], true)) {
                $posCounts[$slot] = ($posCounts[$slot] ?? 0) + 1;
            } elseif (in_array($slot, ['FLEX', 'WR_RB', 'WR_TE', 'RB_TE', 'REC_FLEX'], true)) {
                // allocate fractional counts to RB/WR/TE
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
        // Determine replacement thresholds by position from projections
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
            $index = max(0, (int) round(($posCounts[$pos] ?? 0) * 12)); // assume 12 teams
            $replacement[$pos] = $pool[$index] ?? 0.0;
        }

        // Simple pick horizon: expected picks until next turn
        $round = (int) ($arguments['round'] ?? 1);
        $pickInRound = (int) ($arguments['pick_in_round'] ?? 1);
        $roundSize = (int) ($arguments['round_size'] ?? 12);
        $snake = (bool) ($arguments['snake'] ?? true);
        $currentPickOverall = ($round - 1) * $roundSize + $pickInRound;
        $picksUntilNext = $snake
            ? ($round % 2 === 1 ? (2 * $roundSize - $pickInRound * 2 + 1) : (2 * $pickInRound - 1))
            : $roundSize;

        // Monte Carlo availability estimate by position
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

        $adpIndex = [];
        foreach ($adp as $row) {
            $adpIndex[(string) ($row['player_id'] ?? '')] = (float) ($row['adp'] ?? 999.0);
        }
        // Optionally blend ESPN ADP
        $blendAdp = (bool) ($arguments['blend_adp'] ?? true);
        if ($blendAdp) {
            $espnView = (string) ($arguments['espn_view'] ?? 'mDraftDetail');
            $espnPlayers = $espn->getFantasyPlayers((int) $season, $espnView, 2000);
            $nameToPid = [];
            foreach ($catalog as $pidKey => $meta) {
                $pid = (string) ($meta['player_id'] ?? $pidKey);
                $fullName = (string) ($meta['full_name'] ?? trim(($meta['first_name'] ?? '').' '.($meta['last_name'] ?? '')));
                $nameToPid[self::normalizeName($fullName)] = $pid;
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
                $espnName = (string) ($item['fullName'] ?? ($item['player']['fullName'] ?? (($item['firstName'] ?? '').' '.($item['lastName'] ?? ''))));
                $norm = self::normalizeName($espnName);
                $pid = $nameToPid[$norm] ?? null;
                if ($pid === null) {
                    continue;
                }
                // Average with existing ADP when available
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
            // ADP leverage: how far ahead of consensus current pick is vs ADP (scaled)
            $adpLeverage = ($adpVal - $currentPickOverall) / max(1.0, $roundSize);
            // Opportunity cost proxy boosted by run risk for position
            $willBeGone = $adpVal < ($currentPickOverall + $picksUntilNext - 2) ? 1.0 : 0.0;
            $oppCost = ($willBeGone ? max(0.0, $vorp) : 0.0) * (1.0 + 0.5 * ($posRunRisk[$pos] ?? 0.0));
            // Construction pressure: favor underfilled positions
            $construction = match ($pos) {
                'RB' => max(0.0, 3 - ($needCounts['RB'] ?? 0)),
                'WR' => max(0.0, 4 - ($needCounts['WR'] ?? 0)),
                'QB' => max(0.0, 1 - ($needCounts['QB'] ?? 0)),
                'TE' => max(0.0, 1 - ($needCounts['TE'] ?? 0)),
                default => 0.0,
            };
            // Strategy preferences
            $strategy = Cache::get('mcp:strategy', []);

            // Basic bye smoothing: penalize overlapping byes at same position
            $byePenalty = 0.0;
            $bye = $meta['bye_week'] ?? ($meta['bye'] ?? null);
            if (is_numeric($bye) && isset($myByesByPos[$pos][(int) $bye])) {
                $byePenalty = 1.0; // simple indicator penalty
            }

            // Injury penalty from catalog status where present
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

            // Stacking bonus: QB with WR/TE or WR/TE with QB on same team
            $team = strtoupper((string) ($meta['team'] ?? ''));
            $stackBonus = 0.0;
            if ($team !== '') {
                if ($pos === 'QB' && (isset($myTeamsByPos['WR'][$team]) || isset($myTeamsByPos['TE'][$team]))) {
                    $stackBonus = 1.0;
                } elseif (in_array($pos, ['WR', 'TE'], true) && isset($myTeamsByPos['QB'][$team])) {
                    $stackBonus = 1.0;
                }
            }

            // Round-aware weights
            $wV = 1.5;
            $wOpp = 1.0;
            $wADP = max(0.2, 1.0 - 0.05 * ($round - 1));
            $wCons = 0.7;
            $wBye = 0.1;
            $wInj = 0.5;
            $wStack = 0.3;
            // Strategy adjustments
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

        return ['recommendations' => $top, 'pos_run_risk' => $posRunRisk, 'meta' => [
            'current_pick_overall' => $currentPickOverall,
            'picks_until_next' => $picksUntilNext,
            'round' => $round,
            'pick_in_round' => $pickInRound,
            'round_size' => $roundSize,
            'snake' => $snake,
            'blend_adp' => (bool) ($arguments['blend_adp'] ?? true),
        ]];
    }

    private static function normalizeName(string $name): string
    {
        $n = strtolower(trim($name));
        $n = preg_replace('/[^a-z\s]/', '', $n ?? '');
        $n = preg_replace('/\s+/', ' ', $n ?? '');

        return $n ?? '';
    }
}
