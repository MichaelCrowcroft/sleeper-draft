<?php

namespace App\Services;

use App\Integrations\Sleeper\Requests\GetDraftPicks;
use App\Integrations\Sleeper\Requests\GetPlayersCatalog;
use App\Integrations\Sleeper\Requests\GetPlayersTrending;
use App\Integrations\Sleeper\Requests\GetLeague;
use App\Integrations\Sleeper\Requests\GetLeagueDrafts;
use App\Integrations\Sleeper\Requests\GetLeagueMatchups;
use App\Integrations\Sleeper\Requests\GetLeagueRosters;
use App\Integrations\Sleeper\Requests\GetLeagueTransactions;
use App\Integrations\Sleeper\Requests\GetUserByUsername;
use App\Integrations\Sleeper\Requests\GetUserLeagues;
use App\Integrations\Sleeper\Requests\GetWeeklyProjections;
use App\Integrations\Sleeper\Requests\GetAdp;
use App\Integrations\Sleeper\Requests\GetState;
use App\Integrations\Sleeper\SleeperConnector;
use Illuminate\Support\Facades\Cache;
use OPGG\LaravelMcpServer\Exceptions\Enums\JsonRpcErrorCode;
use OPGG\LaravelMcpServer\Exceptions\JsonRpcErrorException;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Response;

class SleeperSdk
{
    public function __construct(private readonly SleeperConnector $connector) {}

    private function fetchCached(string $cacheKey, int $ttlSeconds, callable $callback): mixed
    {
        return Cache::remember($cacheKey, now()->addSeconds($ttlSeconds), function () use ($callback) {
            try {
                return $callback();
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

    public function getUserByUsername(string $username, int $ttlSeconds = 86400): array
    {
        $cacheKey = 'sleeper:user:'.$username;
        $data = $this->fetchCached($cacheKey, $ttlSeconds, function () use ($username) {
            $response = $this->connector->send(new GetUserByUsername($username));
            return $this->safeJson($response);
        });

        if (! is_array($data) || empty($data['user_id'])) {
            throw new JsonRpcErrorException(message: 'User not found', code: JsonRpcErrorCode::INVALID_REQUEST);
        }

        return $data;
    }

    public function getUserLeagues(string $userId, string $sport, string $season, int $ttlSeconds = 300): array
    {
        $cacheKey = 'sleeper:user:'.$userId.':leagues:'.$sport.':'.$season;
        $data = $this->fetchCached($cacheKey, $ttlSeconds, function () use ($userId, $sport, $season) {
            $response = $this->connector->send(new GetUserLeagues($userId, $sport, $season));
            return $this->safeJson($response);
        });
        return is_array($data) ? $data : [];
    }

    public function getLeague(string $leagueId, int $ttlSeconds = 300): array
    {
        $cacheKey = 'sleeper:league:'.$leagueId;
        $data = $this->fetchCached($cacheKey, $ttlSeconds, function () use ($leagueId) {
            $response = $this->connector->send(new GetLeague($leagueId));
            return $this->safeJson($response);
        });
        if (! is_array($data) || empty($data['league_id'])) {
            throw new JsonRpcErrorException(message: 'League not found', code: JsonRpcErrorCode::INVALID_REQUEST);
        }
        return $data;
    }

    public function getLeagueRosters(string $leagueId, int $ttlSeconds = 120): array
    {
        $cacheKey = 'sleeper:league:'.$leagueId.':rosters';
        $data = $this->fetchCached($cacheKey, $ttlSeconds, function () use ($leagueId) {
            $response = $this->connector->send(new GetLeagueRosters($leagueId));
            return $this->safeJson($response);
        });
        return is_array($data) ? $data : [];
    }

    public function getLeagueMatchups(string $leagueId, int $week, int $ttlSeconds = 60): array
    {
        $cacheKey = 'sleeper:league:'.$leagueId.':matchups:'.$week;
        $data = $this->fetchCached($cacheKey, $ttlSeconds, function () use ($leagueId, $week) {
            $response = $this->connector->send(new GetLeagueMatchups($leagueId, $week));
            return $this->safeJson($response);
        });
        return is_array($data) ? $data : [];
    }

    public function getLeagueTransactions(string $leagueId, int $week, int $ttlSeconds = 60): array
    {
        $cacheKey = 'sleeper:league:'.$leagueId.':transactions:'.$week;
        $data = $this->fetchCached($cacheKey, $ttlSeconds, function () use ($leagueId, $week) {
            $response = $this->connector->send(new GetLeagueTransactions($leagueId, $week));
            return $this->safeJson($response);
        });
        return is_array($data) ? $data : [];
    }

    public function getLeagueDrafts(string $leagueId, int $ttlSeconds = 86400): array
    {
        $cacheKey = 'sleeper:league:'.$leagueId.':drafts';
        $data = $this->fetchCached($cacheKey, $ttlSeconds, function () use ($leagueId) {
            $response = $this->connector->send(new GetLeagueDrafts($leagueId));
            return $this->safeJson($response);
        });
        return is_array($data) ? $data : [];
    }

    public function getDraftPicks(string $draftId, int $ttlSeconds = 86400): array
    {
        $cacheKey = 'sleeper:draft:'.$draftId.':picks';
        $data = $this->fetchCached($cacheKey, $ttlSeconds, function () use ($draftId) {
            $response = $this->connector->send(new GetDraftPicks($draftId));
            return $this->safeJson($response);
        });
        return is_array($data) ? $data : [];
    }

    public function getPlayersCatalog(string $sport = 'nfl', int $ttlSeconds = 86400): array
    {
        $cacheKey = 'sleeper:players:catalog:'.$sport;
        $data = $this->fetchCached($cacheKey, $ttlSeconds, function () use ($sport) {
            $response = $this->connector->send(new GetPlayersCatalog($sport));
            return $this->safeJson($response);
        });
        return is_array($data) ? $data : [];
    }

    public function getPlayersTrending(string $type = 'add', string $sport = 'nfl', int $lookbackHours = 24, int $limit = 25, int $ttlSeconds = 900): array
    {
        $cacheKey = "sleeper:players:trending:$sport:$type:$lookbackHours:$limit";
        $data = $this->fetchCached($cacheKey, $ttlSeconds, function () use ($sport, $type, $lookbackHours, $limit) {
            $response = $this->connector->send(new GetPlayersTrending($sport, $type, $lookbackHours, $limit));
            return $this->safeJson($response);
        });
        return is_array($data) ? $data : [];
    }

    public function getWeeklyProjections(string $season, int $week, string $sport = 'nfl', int $ttlSeconds = 600): array
    {
        $cacheKey = "sleeper:projections:$sport:$season:$week";
        $data = $this->fetchCached($cacheKey, $ttlSeconds, function () use ($sport, $season, $week) {
            $response = $this->connector->send(new GetWeeklyProjections($sport, $season, $week));
            return $this->safeJson($response);
        });
        return is_array($data) ? $data : [];
    }

    public function getAdp(string $season, string $format = 'redraft', string $sport = 'nfl', int $ttlSeconds = 86400): array
    {
        $cacheKey = "sleeper:adp:$sport:$season:$format";
        $data = $this->fetchCached($cacheKey, $ttlSeconds, function () use ($sport, $season, $format) {
            $response = $this->connector->send(new GetAdp($sport, $season, $format));
            return $this->safeJson($response);
        });
        return is_array($data) ? $data : [];
    }

    public function getState(string $sport = 'nfl', int $ttlSeconds = 300): array
    {
        $cacheKey = 'sleeper:state:'.$sport;
        $data = $this->fetchCached($cacheKey, $ttlSeconds, function () use ($sport) {
            $response = $this->connector->send(new GetState($sport));
            return $this->safeJson($response);
        });
        return is_array($data) ? $data : [];
    }
}
