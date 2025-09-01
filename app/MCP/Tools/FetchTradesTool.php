<?php

namespace App\MCP\Tools;

use App\Http\Resources\PlayerResource;
use App\Models\Player;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use OPGG\LaravelMcpServer\Exceptions\Enums\JsonRpcErrorCode;
use OPGG\LaravelMcpServer\Exceptions\JsonRpcErrorException;
use OPGG\LaravelMcpServer\Services\ToolService\ToolInterface;

class FetchTradesTool implements ToolInterface
{
    public function isStreaming(): bool
    {
        return false;
    }

    public function name(): string
    {
        return 'fetch-trades';
    }

    public function description(): string
    {
        return 'Fetches trades for a league and returns trade details with expanded player information. Set `pending_only` to true to limit results to pending trades.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'league_id' => [
                    'type' => 'string',
                    'description' => 'Sleeper league ID to fetch trades for',
                ],
                'round' => [
                    'anyOf' => [
                        ['type' => 'integer', 'minimum' => 1],
                        ['type' => 'null'],
                    ],
                    'description' => 'Week/round to fetch transactions for (defaults to 1)',
                    'default' => 1,
                ],
                'roster_id' => [
                    'anyOf' => [
                        ['type' => 'integer'],
                        ['type' => 'null'],
                    ],
                    'description' => 'Optional: filter trades involving this roster ID',
                ],
                'pending_only' => [
                    'type' => 'boolean',
                    'description' => 'If true, only include trades with status pending',
                    'default' => false,
                ],
            ],
            'required' => ['league_id'],
        ];
    }

    public function annotations(): array
    {
        return [
            'title' => 'Fetch League Trades',
            'readOnlyHint' => true,
            'destructiveHint' => false,
            'idempotentHint' => true,
            'openWorldHint' => true,
            'category' => 'fantasy-sports',
            'data_source' => 'external_api_and_database',
            'cache_recommended' => false,
        ];
    }

    public function execute(array $arguments): mixed
    {
        $validator = Validator::make($arguments, [
            'league_id' => ['required', 'string'],
            'round' => ['nullable', 'integer', 'min:1'],
            'roster_id' => ['nullable', 'integer'],
            'pending_only' => ['boolean'],
        ]);

        if ($validator->fails()) {
            throw new JsonRpcErrorException(
                message: 'Validation failed: '.$validator->errors()->first(),
                code: JsonRpcErrorCode::INVALID_REQUEST
            );
        }

        $leagueId = $arguments['league_id'];
        $round = $arguments['round'] ?? 1;
        $filterRoster = $arguments['roster_id'] ?? null;
        $pendingOnly = $arguments['pending_only'] ?? false;

        $response = Http::get("https://api.sleeper.app/v1/league/{$leagueId}/transactions/{$round}");

        if (! $response->successful()) {
            throw new JsonRpcErrorException(
                message: 'Failed to fetch transactions from Sleeper API',
                code: JsonRpcErrorCode::INTERNAL_ERROR
            );
        }

        $transactions = $response->json();

        if (! is_array($transactions)) {
            throw new JsonRpcErrorException(
                message: 'Invalid transaction data received from API',
                code: JsonRpcErrorCode::INTERNAL_ERROR
            );
        }

        $trades = array_filter($transactions, function ($tx) use ($filterRoster, $pendingOnly) {
            if (($tx['type'] ?? null) !== 'trade') {
                return false;
            }

            if ($pendingOnly && ($tx['status'] ?? null) !== 'pending') {
                return false;
            }

            if ($filterRoster !== null) {
                return in_array($filterRoster, $tx['roster_ids'] ?? []);
            }

            return true;
        });

        // Gather all player IDs to expand
        $allPlayerIds = [];
        foreach ($trades as $trade) {
            if (isset($trade['adds']) && is_array($trade['adds'])) {
                $allPlayerIds = array_merge($allPlayerIds, array_keys($trade['adds']));
            }
            if (isset($trade['drops']) && is_array($trade['drops'])) {
                $allPlayerIds = array_merge($allPlayerIds, array_keys($trade['drops']));
            }
        }
        $allPlayerIds = array_unique($allPlayerIds);

        $playersFromDb = [];
        if (! empty($allPlayerIds)) {
            $playersFromDb = Player::whereIn('player_id', $allPlayerIds)
                ->get()
                ->mapWithKeys(fn ($p) => [
                    $p->player_id => (new PlayerResource($p))->resolve(),
                ])
                ->all();
        }

        $resultTrades = [];
        foreach ($trades as $trade) {
            $resultTrades[] = [
                'transaction_id' => $trade['transaction_id'] ?? null,
                'status' => $trade['status'] ?? null,
                'roster_ids' => $trade['roster_ids'] ?? [],
                'adds' => $this->transformPlayers($trade['adds'] ?? [], $playersFromDb, 'to_roster_id'),
                'drops' => $this->transformPlayers($trade['drops'] ?? [], $playersFromDb, 'from_roster_id'),
                'draft_picks' => $trade['draft_picks'] ?? [],
                'waiver_budget' => $trade['waiver_budget'] ?? [],
            ];
        }

        return [
            'success' => true,
            'data' => array_values($resultTrades),
            'count' => count($resultTrades),
            'metadata' => [
                'league_id' => $leagueId,
                'round' => $round,
                'filtered_roster_id' => $filterRoster,
                'pending_only' => $pendingOnly,
                'executed_at' => now()->toISOString(),
            ],
        ];
    }

    private function transformPlayers(array $playerMap, array $playersFromDb, string $rosterKey): array
    {
        $enhanced = [];
        foreach ($playerMap as $playerId => $rid) {
            $enhanced[] = [
                'player_id' => (string) $playerId,
                $rosterKey => $rid,
                'player' => $playersFromDb[$playerId] ?? null,
            ];
        }

        return $enhanced;
    }
}
