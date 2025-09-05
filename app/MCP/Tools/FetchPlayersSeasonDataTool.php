<?php

namespace App\MCP\Tools;

use App\Http\Resources\PlayerResource;
use App\Models\Player;
use Illuminate\Support\Facades\Validator;
use OPGG\LaravelMcpServer\Exceptions\Enums\JsonRpcErrorCode;
use OPGG\LaravelMcpServer\Exceptions\JsonRpcErrorException;
use OPGG\LaravelMcpServer\Services\ToolService\ToolInterface;

class FetchPlayersSeasonDataTool implements ToolInterface
{
    public function isStreaming(): bool
    {
        return false;
    }

    public function name(): string
    {
        return 'fetch-players-season-data';
    }

    public function description(): string
    {
        return 'Returns all players with last season stats + summary and current season projections + summary. Optionally filter by position.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'position' => [
                    'type' => 'string',
                    'description' => 'Optional position filter (e.g., QB, RB, WR, TE, K, DEF)'
                ],
                'limit' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => 1000,
                    'description' => 'Optional limit of players to return (default 10)'
                ],
                'offset' => [
                    'type' => 'integer',
                    'minimum' => 0,
                    'description' => 'Optional offset for pagination (default 0)'
                ],
                'cursor' => [
                    'type' => 'string',
                    'description' => 'Opaque cursor for pagination per MCP spec. If provided, overrides offset/limit.'
                ],
            ],
        ];
    }

    public function annotations(): array
    {
        return [
            'title' => 'Fetch Players Season Data',
            'readOnlyHint' => true,
            'destructiveHint' => false,
            'idempotentHint' => true,
            'openWorldHint' => false,
            'category' => 'fantasy-sports',
            'data_source' => 'database',
            'cache_recommended' => true,
        ];
    }

    public function execute(array $arguments): mixed
    {
        $arguments = $this->normalizeArguments($arguments);

        $validator = Validator::make($arguments, [
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

        $position = $arguments['position'] ?? null;
        $cursor = $arguments['cursor'] ?? null;

        // Determine paging from cursor or fallback to limit/offset
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
            // Position in cursor (if present) takes precedence
            if (isset($decoded['position'])) {
                $position = $decoded['position'];
            }
        }

        // Enforce sane bounds
        $limit = max(1, min(1000, $limit));
        $offset = max(0, $offset);

        $query = Player::query();

        if ($position) {
            $query->where('position', strtoupper($position));
        }

        // Eager-load last season stats and current season projections
        $query->with(['stats2024', 'projections2025']);

        // Fetch one extra to detect if there is a next page
        $pagePlusOne = $limit + 1;
        $results = $query->orderBy('search_rank')
            ->offset($offset)
            ->limit($pagePlusOne)
            ->get();

        $hasMore = $results->count() > $limit;
        $players = $hasMore ? $results->slice(0, $limit)->values() : $results;

        // Transform using PlayerResource which includes season summaries
        $data = $players->map(fn ($player) => (new PlayerResource($player))->resolve())->all();

        $nextCursor = null;
        if ($hasMore) {
            $nextCursor = $this->encodeCursor([
                'offset' => $offset + $limit,
                'limit' => $limit,
                'position' => $position,
            ]);
        }

        return [
            'success' => true,
            'operation' => 'fetch-players-season-data',
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
                'executed_at' => now()->toISOString(),
            ],
            'count' => count($data),
            'players' => $data,
            'nextCursor' => $nextCursor,
        ];
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
