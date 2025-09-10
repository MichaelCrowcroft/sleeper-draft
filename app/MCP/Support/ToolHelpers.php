<?php

namespace App\MCP\Support;

use App\Actions\Matchups\DetermineCurrentWeek;
use Illuminate\Support\Facades\Validator;
use OPGG\LaravelMcpServer\Exceptions\Enums\JsonRpcErrorCode;
use OPGG\LaravelMcpServer\Exceptions\JsonRpcErrorException;

trait ToolHelpers
{
    /**
     * Build a standardized MCP tool response.
     *
     * Keys:
     * - success: boolean
     * - data: mixed (array|object)
     * - count: int (optional)
     * - message: string (optional)
     * - metadata: array (optional, auto-includes executed_at if missing)
     */
    protected function buildResponse(mixed $data, ?int $count = null, ?string $message = null, array $metadata = []): array
    {
        if (! array_key_exists('executed_at', $metadata)) {
            $metadata['executed_at'] = now()->toISOString();
        }

        $response = [
            'success' => true,
            'data' => $data,
            'metadata' => $metadata,
        ];

        if ($count !== null) {
            $response['count'] = $count;
        }

        if ($message !== null) {
            $response['message'] = $message;
        }

        return $response;
    }

    /**
     * Convenience for list responses: computes count and sets data array.
     */
    protected function buildListResponse(array $items, ?string $message = null, array $metadata = []): array
    {
        return $this->buildResponse(
            data: $items,
            count: count($items),
            message: $message,
            metadata: $metadata,
        );
    }

    /**
     * Normalize arguments provided to a tool, with optional alias mapping and type coercion.
     * - Parses raw single-string payloads (JSON or key=value tokens)
     * - Applies alias mapping (e.g., leagueId => league_id)
     * - Coerces types for keys in $intKeys, $boolKeys, $stringKeys
     */
    protected function normalizeArgumentsGeneric(
        array $arguments,
        array $aliases = [],
        array $intKeys = [],
        array $boolKeys = [],
        array $stringKeys = []
    ): array {
        $arguments = $this->parseRawArgumentsIfPresent($arguments);

        // Apply aliases
        $normalized = [];
        foreach ($arguments as $key => $value) {
            $normalized[$aliases[$key] ?? $key] = $value;
        }

        // Coerce types
        foreach ($intKeys as $k) {
            if (isset($normalized[$k]) && is_string($normalized[$k])) {
                $normalized[$k] = (int) $normalized[$k];
            }
        }
        foreach ($boolKeys as $k) {
            if (isset($normalized[$k])) {
                if (is_string($normalized[$k])) {
                    $v = strtolower($normalized[$k]);
                    $normalized[$k] = $v === 'true' ? true : ($v === 'false' ? false : null);
                } else {
                    $normalized[$k] = (bool) $normalized[$k];
                }
            }
        }
        foreach ($stringKeys as $k) {
            if (isset($normalized[$k])) {
                $normalized[$k] = (string) $normalized[$k];
            }
        }

        return $normalized;
    }

    /**
     * Validate input against rules and throw MCP-friendly error on failure.
     * Returns validated (but not transformed) data.
     */
    protected function validateOrFail(array $data, array $rules): array
    {
        $validator = Validator::make($data, $rules);
        if ($validator->fails()) {
            throw new JsonRpcErrorException(
                message: 'Validation failed: '.$validator->errors()->first(),
                code: JsonRpcErrorCode::INVALID_REQUEST
            );
        }

        return $validator->validated();
    }

    /**
     * If a single raw string argument is provided, parse it as JSON or key=value pairs.
     */
    protected function parseRawArgumentsIfPresent(array $arguments): array
    {
        if (count($arguments) === 1 && array_key_exists(0, $arguments) && is_string($arguments[0])) {
            $raw = trim((string) $arguments[0]);
            $raw = preg_replace('/^\s*Arguments\s*/i', '', $raw ?? '');

            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }

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
                return $pairs;
            }
        }

        return $arguments;
    }

    /** Encode an MCP paging cursor payload to a base64 string. */
    protected function encodeCursor(array $payload): string
    {
        return base64_encode(json_encode($payload));
    }

    /** Decode an MCP paging cursor payload from a base64 string. */
    protected function decodeCursor(string $cursor): ?array
    {
        try {
            $decoded = base64_decode($cursor, true);
            if ($decoded === false || $decoded === '') {
                return null;
            }
            $arr = json_decode($decoded, true);

            return is_array($arr) ? $arr : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** Resolve current week from Sleeper via shared action with caching. */
    protected function getCurrentWeek(string $sport = 'nfl'): int
    {
        try {
            $state = app(DetermineCurrentWeek::class)->execute($sport);

            return (int) ($state['week'] ?? 1);
        } catch (\Throwable) {
            return 1;
        }
    }

    /** Resolve current season from Sleeper via shared action with caching. */
    protected function getCurrentSeason(string $sport = 'nfl'): string
    {
        try {
            $state = app(DetermineCurrentWeek::class)->execute($sport);

            return (string) ($state['season'] ?? date('Y'));
        } catch (\Throwable) {
            return (string) date('Y');
        }
    }
}
