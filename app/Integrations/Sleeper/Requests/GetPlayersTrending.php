<?php

namespace App\Integrations\Sleeper\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetPlayersTrending extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        protected string $sport = 'nfl',
        protected string $type = 'add', // add|drop
        protected int $lookbackHours = 24,
        protected int $limit = 25
    ) {}

    public function resolveEndpoint(): string
    {
        return "/v1/players/{$this->sport}/trending/{$this->type}";
    }

    protected function defaultQuery(): array
    {
        return [
            'lookback_hours' => $this->lookbackHours,
            'limit' => $this->limit,
        ];
    }
}
