<?php

namespace App\Integrations\Espn\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetFantasyPlayers extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        protected int $season,
        protected string $view = 'mDraftDetail',
        protected ?int $limit = null,
        protected ?array $fantasyFilter = null,
    ) {}

    public function resolveEndpoint(): string
    {
        $query = [
            'view' => $this->view,
        ];

        // ESPN defaults to 50 items unless X-Fantasy-Filter is used. Allow a simple limit option
        if ($this->limit !== null) {
            $query['limit'] = $this->limit;
        }

        $suffix = http_build_query($query);

        return "/apis/v3/games/ffl/seasons/{$this->season}/players?{$suffix}";
    }

    public function defaultHeaders(): array
    {
        $headers = [
            'Accept' => 'application/json',
        ];

        if ($this->fantasyFilter !== null) {
            $headers['X-Fantasy-Filter'] = json_encode($this->fantasyFilter);
        }

        return $headers;
    }
}
