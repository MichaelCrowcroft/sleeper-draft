<?php

namespace App\Services;

use App\Integrations\Espn\EspnCoreConnector;
use App\Integrations\Espn\EspnFantasyConnector;
use App\Integrations\Espn\Requests\GetCoreAthletes;
use App\Integrations\Espn\Requests\GetFantasyPlayers;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use OPGG\LaravelMcpServer\Exceptions\Enums\JsonRpcErrorCode;
use OPGG\LaravelMcpServer\Exceptions\JsonRpcErrorException;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Response;

class EspnSdk
{
    public function __construct(
        private readonly EspnCoreConnector $core,
        private readonly EspnFantasyConnector $fantasy,
    ) {}

    private function fetchCached(string $cacheKey, int $ttlSeconds, callable $callback, array $tags = []): mixed
    {
        $repository = Cache::store();
        $underlying = method_exists($repository, 'getStore') ? $repository->getStore() : null;
        $supportsTags = $underlying instanceof \Illuminate\Cache\TaggableStore;
        $cache = ($supportsTags && ! empty($tags)) ? Cache::tags($tags) : Cache::store();

        return $cache->remember($cacheKey, now()->addSeconds($ttlSeconds), function () use ($callback) {
            try {
                return $this->withRetries($callback);
            } catch (RequestException $e) {
                throw new JsonRpcErrorException(message: 'ESPN request failed: '.$e->getMessage(), code: JsonRpcErrorCode::INTERNAL_ERROR);
            } catch (\Throwable $e) {
                throw new JsonRpcErrorException(message: 'ESPN request failed: '.$e->getMessage(), code: JsonRpcErrorCode::INTERNAL_ERROR);
            }
        });
    }

    private function safeJson(Response $response): array
    {
        $body = $response->body();
        if ($body === null || $body === '') {
            return [];
        }
        $decoded = json_decode($body, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function withRetries(callable $op): mixed
    {
        $max = (int) Config::get('services.espn.retry.max_attempts', 3);
        $baseMs = (int) Config::get('services.espn.retry.base_ms', 200);
        $maxMs = (int) Config::get('services.espn.retry.max_ms', 2000);
        $attempt = 0;
        beginning:
        try {
            $attempt++;

            return $op();
        } catch (RequestException $e) {
            $code = $e->getCode();
            $shouldRetry = in_array($code, [0, 408, 429, 500, 502, 503, 504], true);
            if ($shouldRetry && $attempt < $max) {
                $sleepMs = min($maxMs, (int) ($baseMs * pow(2, $attempt - 1)) + random_int(0, $baseMs));
                usleep($sleepMs * 1000);
                goto beginning;
            }
            throw $e;
        }
    }

    public function getCoreAthletes(int $page = 1, int $limit = 20000, ?int $ttlSeconds = null): array
    {
        $ttlSeconds = $ttlSeconds ?? (int) Config::get('services.espn.ttl.core_athletes', 86400);
        $cacheKey = "espn:core:athletes:$page:$limit";
        $data = $this->fetchCached($cacheKey, $ttlSeconds, function () use ($page, $limit) {
            $response = $this->core->send(new GetCoreAthletes(page: $page, limit: $limit));

            return $this->safeJson($response);
        }, tags: ['espn', 'core', 'athletes']);

        return is_array($data) ? $data : [];
    }

    // Partners API removed (required API key)

    public function getFantasyPlayers(int $season, string $view = 'mDraftDetail', ?int $limit = null, ?array $fantasyFilter = null, ?int $ttlSeconds = null): array
    {
        $ttlSeconds = $ttlSeconds ?? (int) Config::get('services.espn.ttl.fantasy_players', 3600);
        $filterHash = $fantasyFilter ? md5(json_encode($fantasyFilter)) : 'none';
        $limitKey = $limit ?? 'default';
        $cacheKey = "espn:fantasy:players:$season:$view:$limitKey:$filterHash";
        $data = $this->fetchCached($cacheKey, $ttlSeconds, function () use ($season, $view, $limit, $fantasyFilter) {
            $response = $this->fantasy->send(new GetFantasyPlayers(season: $season, view: $view, limit: $limit, fantasyFilter: $fantasyFilter));

            return $this->safeJson($response);
        }, tags: ['espn', 'fantasy', 'players', 'season:'.$season]);

        return is_array($data) ? $data : [];
    }
}
