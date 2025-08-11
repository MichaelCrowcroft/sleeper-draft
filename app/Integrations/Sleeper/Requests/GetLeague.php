<?php

namespace App\Integrations\Sleeper\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetLeague extends Request
{
    protected Method $method = Method::GET;

    public function __construct(protected string $leagueId) {}

    public function resolveEndpoint(): string
    {
        return "/v1/league/{$this->leagueId}";
    }
}
