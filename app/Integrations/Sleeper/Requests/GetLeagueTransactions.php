<?php

namespace App\Integrations\Sleeper\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetLeagueTransactions extends Request
{
    protected Method $method = Method::GET;

    public function __construct(protected string $leagueId, protected int $week) {}

    public function resolveEndpoint(): string
    {
        return "/v1/league/{$this->leagueId}/transactions/{$this->week}";
    }
}
