<?php

namespace App\Integrations\Espn\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetCoreAthletes extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        protected string $sport = 'football',
        protected string $league = 'nfl',
        protected int $page = 1,
        protected int $limit = 20000,
    ) {}

    public function resolveEndpoint(): string
    {
        return "/v3/sports/{$this->sport}/{$this->league}/athletes?page={$this->page}&limit={$this->limit}";
    }
}
