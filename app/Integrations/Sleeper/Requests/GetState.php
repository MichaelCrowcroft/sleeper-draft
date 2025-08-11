<?php

namespace App\Integrations\Sleeper\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetState extends Request
{
    protected Method $method = Method::GET;

    public function __construct(protected string $sport = 'nfl') {}

    public function resolveEndpoint(): string
    {
        return "/v1/state/{$this->sport}";
    }
}
