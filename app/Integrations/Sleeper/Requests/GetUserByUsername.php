<?php

namespace App\Integrations\Sleeper\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetUserByUsername extends Request
{
    protected Method $method = Method::GET;

    public function __construct(protected string $username) {}

    public function resolveEndpoint(): string
    {
        return "/v1/user/{$this->username}";
    }
}
