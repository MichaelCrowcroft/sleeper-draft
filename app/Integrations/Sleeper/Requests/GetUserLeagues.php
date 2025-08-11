<?php

namespace App\Integrations\Sleeper\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetUserLeagues extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        protected string $userId,
        protected string $sport,
        protected string $season,
    ) {}

    public function resolveEndpoint(): string
    {
        return "/v1/user/{$this->userId}/leagues/{$this->sport}/{$this->season}";
    }
}
