<?php

namespace App\Integrations\Sleeper\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetAdp extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        protected string $sport,
        protected string $season,
        protected string $format = 'redraft' // redraft|dynasty|bestball
    ) {}

    public function resolveEndpoint(): string
    {
        return "/v1/adp/{$this->sport}/{$this->season}?format={$this->format}";
    }
}
