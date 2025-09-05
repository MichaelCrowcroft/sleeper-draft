<?php

namespace App\MCP\Tools;

use App\Http\Resources\PlayerResource;
use App\Models\Player;
use Illuminate\Support\Facades\Validator;
use OPGG\LaravelMcpServer\Exceptions\Enums\JsonRpcErrorCode;
use OPGG\LaravelMcpServer\Exceptions\JsonRpcErrorException;
use OPGG\LaravelMcpServer\Services\ToolService\ToolInterface;

class FetchPlayerSeasonDataTool implements ToolInterface
{
    public function isStreaming(): bool
    {
        return false;
    }

    public function name(): string
    {
        return 'fetch-player-season-data';
    }

    public function description(): string
    {
        return 'Returns last season stats + summary and current season projections + summary for a specific player by player_id or by name. If name matches multiple, all matches are returned.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'player_id' => [
                    'type' => 'string',
                    'description' => 'Sleeper player ID to search for (exact match)'
                ],
                'name' => [
                    'type' => 'string',
                    'description' => 'Player name to search (case-insensitive partial match)'
                ],
            ],
        ];
    }

    public function annotations(): array
    {
        return [
            'title' => 'Fetch Player Season Data',
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
            'player_id' => ['nullable', 'string'],
            'name' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            throw new JsonRpcErrorException(
                message: 'Validation failed: '.$validator->errors()->first(),
                code: JsonRpcErrorCode::INVALID_REQUEST
            );
        }

        $playerId = $arguments['player_id'] ?? null;
        $name = $arguments['name'] ?? null;

        if (! $playerId && ! $name) {
            throw new JsonRpcErrorException(
                message: 'Provide either player_id or name',
                code: JsonRpcErrorCode::INVALID_REQUEST
            );
        }

        // Build base query eager loading summaries
        $query = Player::query()->with(['stats2024', 'projections2025']);

        $mode = null;
        if ($playerId) {
            $mode = 'by_id';
            $query->where('player_id', $playerId);
        } else {
            $mode = 'by_name';
            $needle = trim((string) $name);
            $query->where(function ($q) use ($needle) {
                $q->where('full_name', 'like', '%'.$needle.'%')
                    ->orWhere('search_full_name', 'like', '%'.strtolower($needle).'%')
                    ->orWhere('first_name', 'like', '%'.$needle.'%')
                    ->orWhere('last_name', 'like', '%'.$needle.'%');
            })->orderBy('search_rank');
        }

        $players = $query->get();

        if ($playerId && $players->isEmpty()) {
            throw new JsonRpcErrorException(
                message: 'Player not found for player_id='.$playerId,
                code: JsonRpcErrorCode::INVALID_REQUEST
            );
        }

        $data = $players->map(fn ($p) => (new PlayerResource($p))->resolve())->all();

        return [
            'success' => true,
            'operation' => 'fetch-player-season-data',
            'mode' => $mode,
            'count' => count($data),
            'players' => $data,
            'metadata' => [
                'filters' => [
                    'player_id' => $playerId,
                    'name' => $name,
                ],
                'seasons' => [
                    'stats' => 2024,
                    'projections' => 2025,
                ],
                'executed_at' => now()->toISOString(),
            ],
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
            'id' => 'player_id',
            'playerId' => 'player_id',
        ];
        $normalized = [];
        foreach ($arguments as $key => $value) {
            $normalized[$aliases[$key] ?? $key] = $value;
        }

        if (isset($normalized['player_id'])) {
            $normalized['player_id'] = (string) $normalized['player_id'];
        }

        if (isset($normalized['name'])) {
            $normalized['name'] = (string) $normalized['name'];
        }

        return $normalized;
    }
}
