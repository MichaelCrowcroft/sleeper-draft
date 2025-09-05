<?php

namespace App\MCP\Tools;

use App\Http\Resources\PlayerResource;
use App\Models\Player;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use OPGG\LaravelMcpServer\Exceptions\Enums\JsonRpcErrorCode;
use OPGG\LaravelMcpServer\Exceptions\JsonRpcErrorException;
use OPGG\LaravelMcpServer\Services\ToolService\ToolInterface;

class FetchFreeAgentsPlayersSeasonDataTool implements ToolInterface
{
    public function isStreaming(): bool
    {
        return false;
    }

    public function name(): string
    {
        return 'fetch-free-agents-players-season-data';
    }

    public function description(): string
    {
        return 'Returns players with last season stats + summary and current season projections + summary who are not currently rostered in the specified league. Optionally filter by position and paginate.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'league_id' => [
                    'type' => 'string',
                    'description' => 'Sleeper league ID used to determine currently rostered players (to exclude)',
                ],
                'position' => [
                    'type' => 'string',
                    'description' => 'Optional position filter (e.g., QB, RB, WR, TE, K, DEF)',
                ],
                'limit' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => 1000,
                    'description' => 'Optional limit of players to return (default 10)',
                ],
                'offset' => [
                    'type' => 'integer',
                    'minimum' => 0,
                    'description' => 'Optional offset for pagination (default 0)',
                ],
                'cursor' => [
                    'type' => 'string',
                    'description' => 'Opaque cursor for pagination per MCP spec. If provided, overrides offset/limit.',
                ],
            ],
            'required' => ['league_id'],
        ];
    }

    public function annotations(): array
    {
        return [
            'title' => 'Fetch Free Agents Players Season Data',
            'readOnlyHint' => true,
            'destructiveHint' => false,
            'idempotentHint' => true,
            'openWorldHint' => true, // calls Sleeper API for rosters
            'category' => 'fantasy-sports',
            'data_source' => 'database_and_external_api',
            'cache_recommended' => true,
        ];
    }

    public function execute(array $arguments): mixed
    {
        $arguments = $this->normalizeArguments($arguments);

        // Decode cursor first so that validation can pass when only a cursor is provided
        $position = $arguments['position'] ?? null;
        $cursor = $arguments['cursor'] ?? null;
        $leagueId = isset($arguments['league_id']) ? (string) $arguments['league_id'] : null;

        $defaultPageSize = 10;
        $limit = (int) ($arguments['limit'] ?? $defaultPageSize);
        $offset = (int) ($arguments['offset'] ?? 0);
        if (is_string($cursor) && $cursor !== '') {
            $decoded = $this->decodeCursor($cursor);
            if ($decoded === null) {
                throw new JsonRpcErrorException(
                    message: 'Invalid cursor',
                    code: JsonRpcErrorCode::INVALID_REQUEST
                );
            }
            $limit = (int) ($decoded['limit'] ?? $defaultPageSize);
            $offset = (int) ($decoded['offset'] ?? 0);
            if (isset($decoded['position'])) {
                $position = $decoded['position'];
            }
            if (isset($decoded['league_id'])) {
                $leagueId = (string) $decoded['league_id'];
                $arguments['league_id'] = $leagueId; // ensure presence for validation
            }
        }

        $validator = Validator::make($arguments, [
            'league_id' => ['required', 'string'],
            'position' => ['nullable', 'string'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'offset' => ['nullable', 'integer', 'min:0'],
        ]);

        if ($validator->fails()) {
            throw new JsonRpcErrorException(
                message: 'Validation failed: '.$validator->errors()->first(),
                code: JsonRpcErrorCode::INVALID_REQUEST
            );
        }

        $leagueId = (string) $leagueId; // safe after validation

        $limit = max(1, min(1000, $limit));
        $offset = max(0, $offset);

        // Fetch all rostered player_ids for the league (flattened unique set)
        $rosteredIds = $this->fetchRosteredPlayerIds($leagueId);

        $query = Player::query();
        if ($position) {
            $query->where('position', strtoupper($position));
        }
        if (! empty($rosteredIds)) {
            $query->whereNotIn('player_id', $rosteredIds);
        }

        $query->with(['stats2024', 'projections2025']);

        $pagePlusOne = $limit + 1;
        $results = $query->orderBy('search_rank')
            ->offset($offset)
            ->limit($pagePlusOne)
            ->get();

        $hasMore = $results->count() > $limit;
        $players = $hasMore ? $results->slice(0, $limit)->values() : $results;
        $data = $players->map(fn ($player) => (new PlayerResource($player))->resolve())->all();

        $nextCursor = null;
        if ($hasMore) {
            $nextCursor = $this->encodeCursor([
                'offset' => $offset + $limit,
                'limit' => $limit,
                'position' => $position,
                'league_id' => $leagueId,
            ]);
        }

        return [
            'success' => true,
            'operation' => 'fetch-free-agents-players-season-data',
            'metadata' => [
                'filters' => [
                    'position' => $position,
                ],
                'pagination' => [
                    'limit' => $limit,
                    'offset' => $offset,
                    'mode' => 'cursor',
                ],
                'seasons' => [
                    'stats' => 2024,
                    'projections' => 2025,
                ],
                'league_id' => $leagueId,
                'excluded_rostered_count' => is_array($rosteredIds) ? count($rosteredIds) : 0,
                'executed_at' => now()->toISOString(),
            ],
            'count' => count($data),
            'players' => $data,
            'nextCursor' => $nextCursor,
        ];
    }

    private function fetchRosteredPlayerIds(string $leagueId): array
    {
        try {
            $response = Http::get("https://api.sleeper.app/v1/league/{$leagueId}/rosters");
            if (! $response->successful()) {
                return [];
            }
            $rosters = $response->json();
            if (! is_array($rosters)) {
                return [];
            }

            $ids = [];
            foreach ($rosters as $roster) {
                $players = $roster['players'] ?? [];
                $starters = $roster['starters'] ?? [];
                $bench = $roster['reserve'] ?? [];
                foreach (array_merge($players, $starters, $bench) as $pid) {
                    if (is_string($pid) || is_int($pid)) {
                        $ids[] = (string) $pid;
                    }
                }
            }

            return array_values(array_unique($ids));
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function normalizeArguments(array $arguments): array
    {
        if (count($arguments) === 1 && array_key_exists(0, $arguments) && is_string($arguments[0])) {
            $raw = trim((string) $arguments[0]);
            $raw = preg_replace('/^\s*Arguments\s*/i', '', $raw ?? '');
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $arguments = $decoded;
            } else {
                $pairs = [];
                $pattern = '/([A-Za-z0-9_]+)\s*(?::|=)?\s*(?:\"([^\"]*)\"|\'([^\']*)\'|([0-9]+)|(true|false|null))/i';
                if (preg_match_all($pattern, $raw, $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $m) {
                        $key = $m[1];
                        $val = null;
                        if (($m[2] ?? '') !== '') {
                            $val = $m[2];
                        } elseif (($m[3] ?? '') !== '') {
                            $val = $m[3];
                        } elseif (($m[4] ?? '') !== '') {
                            $val = (int) $m[4];
                        } elseif (($m[5] ?? '') !== '') {
                            $lower = strtolower($m[5]);
                            $val = $lower === 'true' ? true : ($lower === 'false' ? false : null);
                        }
                        $pairs[$key] = $val;
                    }
                }
                if ($pairs !== []) {
                    $arguments = $pairs;
                }
            }
        }

        $aliases = [
            'leagueId' => 'league_id',
            'pos' => 'position',
            'c' => 'cursor',
        ];
        $normalized = [];
        foreach ($arguments as $key => $value) {
            $normalized[$aliases[$key] ?? $key] = $value;
        }

        if (isset($normalized['limit']) && is_string($normalized['limit'])) {
            $normalized['limit'] = (int) $normalized['limit'];
        }
        if (isset($normalized['offset']) && is_string($normalized['offset'])) {
            $normalized['offset'] = (int) $normalized['offset'];
        }
        if (isset($normalized['cursor'])) {
            $normalized['cursor'] = (string) $normalized['cursor'];
        }
        if (isset($normalized['league_id'])) {
            $normalized['league_id'] = (string) $normalized['league_id'];
        }

        return $normalized;
    }

    private function encodeCursor(array $payload): string
    {
        return base64_encode(json_encode($payload));
    }

    private function decodeCursor(string $cursor): ?array
    {
        try {
            $decoded = base64_decode($cursor, true);
            if ($decoded === false || $decoded === '') {
                return null;
            }
            $arr = json_decode($decoded, true);

            return is_array($arr) ? $arr : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
