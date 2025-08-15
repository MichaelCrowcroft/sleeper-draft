<?php

namespace App\Services;

use App\Integrations\Sleeper\Requests\GetAdp;
use App\Integrations\Sleeper\Requests\GetDraftPicks;
use App\Integrations\Sleeper\Requests\GetLeague;
use App\Integrations\Sleeper\Requests\GetLeagueDrafts;
use App\Integrations\Sleeper\Requests\GetLeagueMatchups;
use App\Integrations\Sleeper\Requests\GetLeagueRosters;
use App\Integrations\Sleeper\Requests\GetLeagueTransactions;
use App\Integrations\Sleeper\Requests\GetPlayersCatalog;
use App\Integrations\Sleeper\Requests\GetPlayersTrending;
use App\Integrations\Sleeper\Requests\GetState;
use App\Integrations\Sleeper\Requests\GetUserByUsername;
use App\Integrations\Sleeper\Requests\GetUserLeagues;
use App\Integrations\Sleeper\Requests\GetWeeklyProjections;
use App\Integrations\Sleeper\SleeperConnector;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use OPGG\LaravelMcpServer\Exceptions\Enums\JsonRpcErrorCode;
use OPGG\LaravelMcpServer\Exceptions\JsonRpcErrorException;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Response;

class SleeperSdk
{
    public function __construct(private readonly SleeperConnector $connector) {}

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
                throw new JsonRpcErrorException(message: 'Sleeper request failed: '.$e->getMessage(), code: JsonRpcErrorCode::INTERNAL_ERROR);
            } catch (\Throwable $e) {
                throw new JsonRpcErrorException(message: 'Sleeper request failed: '.$e->getMessage(), code: JsonRpcErrorCode::INTERNAL_ERROR);
            }
        });
    }

    private function safeJson(Response $response): array
    {
        // Prefer reading raw body to avoid typed property issues on null
        $body = $response->body();
        if ($body === null || $body === '') {
            return [];
        }
        $decoded = json_decode($body, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function withRetries(callable $op): mixed
    {
        $max = (int) Config::get('services.sleeper.retry.max_attempts', 3);
        $baseMs = (int) Config::get('services.sleeper.retry.base_ms', 200);
        $maxMs = (int) Config::get('services.sleeper.retry.max_ms', 2000);
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

    public function getUserByUsername(string $username, ?int $ttlSeconds = null): array
    {
        $ttlSeconds = $ttlSeconds ?? (int) Config::get('services.sleeper.ttl.user', 86400);
        $cacheKey = 'sleeper:user:'.$username;
        $data = $this->fetchCached($cacheKey, $ttlSeconds, function () use ($username) {
            $response = $this->connector->send(new GetUserByUsername($username));

            return $this->safeJson($response);
        }, tags: ['sleeper', 'user:'.$username]);

        if (! is_array($data) || empty($data['user_id'])) {
            throw new JsonRpcErrorException(message: 'User not found', code: JsonRpcErrorCode::INVALID_REQUEST);
        }

        return $data;
    }

    public function getUserLeagues(string $userId, string $sport, string $season, ?int $ttlSeconds = null): array
    {
        $ttlSeconds = $ttlSeconds ?? (int) Config::get('services.sleeper.ttl.league', 300);
        $cacheKey = 'sleeper:user:'.$userId.':leagues:'.$sport.':'.$season;
        $data = $this->fetchCached($cacheKey, $ttlSeconds, function () use ($userId, $sport, $season) {
            $response = $this->connector->send(new GetUserLeagues($userId, $sport, $season));

            return $this->safeJson($response);
        }, tags: ['sleeper', 'user:'.$userId, 'season:'.$season]);

        return is_array($data) ? $data : [];
    }

    public function getLeague(string $leagueId, ?int $ttlSeconds = null): array
    {
        $ttlSeconds = $ttlSeconds ?? (int) Config::get('services.sleeper.ttl.league', 300);
        $cacheKey = 'sleeper:league:'.$leagueId;
        $data = $this->fetchCached($cacheKey, $ttlSeconds, function () use ($leagueId) {
            $response = $this->connector->send(new GetLeague($leagueId));

            return $this->safeJson($response);
        }, tags: ['sleeper', 'league:'.$leagueId]);
        if (! is_array($data) || empty($data['league_id'])) {
            throw new JsonRpcErrorException(message: 'League not found', code: JsonRpcErrorCode::INVALID_REQUEST);
        }

        return $data;
    }

    public function getLeagueRosters(string $leagueId, ?int $ttlSeconds = null): array
    {
        $ttlSeconds = $ttlSeconds ?? (int) Config::get('services.sleeper.ttl.rosters', 120);
        $cacheKey = 'sleeper:league:'.$leagueId.':rosters';
        $data = $this->fetchCached($cacheKey, $ttlSeconds, function () use ($leagueId) {
            $response = $this->connector->send(new GetLeagueRosters($leagueId));

            return $this->safeJson($response);
        }, tags: ['sleeper', 'league:'.$leagueId]);

        return is_array($data) ? $data : [];
    }

    public function getLeagueMatchups(string $leagueId, int $week, ?int $ttlSeconds = null): array
    {
        $ttlSeconds = $ttlSeconds ?? (int) Config::get('services.sleeper.ttl.matchups', 60);
        $cacheKey = 'sleeper:league:'.$leagueId.':matchups:'.$week;
        $data = $this->fetchCached($cacheKey, $ttlSeconds, function () use ($leagueId, $week) {
            $response = $this->connector->send(new GetLeagueMatchups($leagueId, $week));

            return $this->safeJson($response);
        }, tags: ['sleeper', 'league:'.$leagueId, 'week:'.$week]);

        return is_array($data) ? $data : [];
    }

    public function getLeagueTransactions(string $leagueId, int $week, ?int $ttlSeconds = null): array
    {
        $ttlSeconds = $ttlSeconds ?? (int) Config::get('services.sleeper.ttl.transactions', 60);
        $cacheKey = 'sleeper:league:'.$leagueId.':transactions:'.$week;
        $data = $this->fetchCached($cacheKey, $ttlSeconds, function () use ($leagueId, $week) {
            $response = $this->connector->send(new GetLeagueTransactions($leagueId, $week));

            return $this->safeJson($response);
        }, tags: ['sleeper', 'league:'.$leagueId, 'week:'.$week]);

        return is_array($data) ? $data : [];
    }

    public function getLeagueDrafts(string $leagueId, ?int $ttlSeconds = null): array
    {
        $ttlSeconds = $ttlSeconds ?? (int) Config::get('services.sleeper.ttl.drafts', 86400);
        $cacheKey = 'sleeper:league:'.$leagueId.':drafts';
        $data = $this->fetchCached($cacheKey, $ttlSeconds, function () use ($leagueId) {
            $response = $this->connector->send(new GetLeagueDrafts($leagueId));

            return $this->safeJson($response);
        }, tags: ['sleeper', 'league:'.$leagueId]);

        return is_array($data) ? $data : [];
    }

    public function getDraftPicks(string $draftId, ?int $ttlSeconds = null): array
    {
        $ttlSeconds = $ttlSeconds ?? (int) Config::get('services.sleeper.ttl.draft_picks', 86400);
        $cacheKey = 'sleeper:draft:'.$draftId.':picks';
        $data = $this->fetchCached($cacheKey, $ttlSeconds, function () use ($draftId) {
            $response = $this->connector->send(new GetDraftPicks($draftId));

            return $this->safeJson($response);
        }, tags: ['sleeper', 'draft:'.$draftId]);

        return is_array($data) ? $data : [];
    }

    public function getPlayersCatalog(string $sport = 'nfl', ?int $ttlSeconds = null): array
    {
        $ttlSeconds = $ttlSeconds ?? (int) Config::get('services.sleeper.ttl.players_catalog', 86400);
        $cacheKey = 'sleeper:players:catalog:'.$sport;
        $data = $this->fetchCached($cacheKey, $ttlSeconds, function () use ($sport) {
            $response = $this->connector->send(new GetPlayersCatalog($sport));

            return $this->safeJson($response);
        }, tags: ['sleeper', 'players', 'sport:'.$sport]);

        return is_array($data) ? $data : [];
    }

    public function getPlayersTrending(string $type = 'add', string $sport = 'nfl', int $lookbackHours = 24, int $limit = 25, ?int $ttlSeconds = null): array
    {
        $ttlSeconds = $ttlSeconds ?? (int) Config::get('services.sleeper.ttl.players_trending', 900);
        $cacheKey = "sleeper:players:trending:$sport:$type:$lookbackHours:$limit";
        $data = $this->fetchCached($cacheKey, $ttlSeconds, function () use ($sport, $type, $lookbackHours, $limit) {
            $response = $this->connector->send(new GetPlayersTrending($sport, $type, $lookbackHours, $limit));

            return $this->safeJson($response);
        }, tags: ['sleeper', 'players', 'sport:'.$sport]);

        return is_array($data) ? $data : [];
    }

    public function getWeeklyProjections(string $season, int $week, string $sport = 'nfl', ?int $ttlSeconds = null): array
    {
        $ttlSeconds = $ttlSeconds ?? (int) Config::get('services.sleeper.ttl.projections', 600);
        $cacheKey = "sleeper:projections:$sport:$season:$week";
        $data = $this->fetchCached($cacheKey, $ttlSeconds, function () use ($sport, $season, $week) {
            $response = $this->connector->send(new GetWeeklyProjections($sport, $season, $week));

            return $this->safeJson($response);
        }, tags: ['sleeper', 'projections', 'season:'.$season, 'week:'.$week]);

        return is_array($data) ? $data : [];
    }

    public function getAdp(string $season, string $format = 'redraft', string $sport = 'nfl', ?int $ttlSeconds = null, bool $allowTrendingFallback = true): array
    {
        $ttlSeconds = $ttlSeconds ?? (int) Config::get('services.sleeper.ttl.adp', 86400);
        $cacheKey = "sleeper:adp:$sport:$season:$format";
        $data = $this->fetchCached($cacheKey, $ttlSeconds, function () use ($sport, $season, $format) {
            $response = $this->connector->send(new GetAdp($sport, $season, $format));

            return $this->safeJson($response);
        }, tags: ['sleeper', 'adp', 'season:'.$season]);

        // If Sleeper's ADP endpoint is unavailable (returns 404/HTML or empty),
        // optionally fall back to a trend-based market ranking so downstream tools continue to work.
        if ((! is_array($data) || empty($data)) && $allowTrendingFallback) {
            // Use trending adds over the last 7 days as a proxy for market interest.
            // This returns an ordered list we can map to a pseudo-ADP rank.
            $lookbackHours = 24 * 7;
            $limit = 500; // cap to a reasonable board size
            $trending = $this->getPlayersTrending('add', $sport, $lookbackHours, $limit);

            $ranked = [];
            $rank = 1;
            foreach ($trending as $row) {
                $playerId = (string) ($row['player_id'] ?? '');
                if ($playerId === '') {
                    continue;
                }
                $ranked[] = [
                    'player_id' => $playerId,
                    'adp' => $rank,
                    'source' => 'fallback_trending',
                ];
                $rank++;
            }

            return $ranked;
        }

        return is_array($data) ? $data : [];
    }

    public function getState(string $sport = 'nfl', ?int $ttlSeconds = null): array
    {
        $ttlSeconds = $ttlSeconds ?? (int) Config::get('services.sleeper.ttl.state', 300);
        $cacheKey = 'sleeper:state:'.$sport;
        $data = $this->fetchCached($cacheKey, $ttlSeconds, function () use ($sport) {
            $response = $this->connector->send(new GetState($sport));

            return $this->safeJson($response);
        }, tags: ['sleeper', 'state', 'sport:'.$sport]);

        return is_array($data) ? $data : [];
    }
}
