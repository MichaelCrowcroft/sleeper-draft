<?php

namespace App\Integrations\Sleeper\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetWeeklyProjections extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        protected string $sport = 'nfl',
        protected string $season,
        protected int $week
    ) {}

    public function resolveEndpoint(): string
    {
        return "/v1/projections/{$this->sport}/{$this->season}/{$this->week}";
    }
}
