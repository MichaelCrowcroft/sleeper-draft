<?php

namespace App\Integrations\Sleeper\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetDraftPicks extends Request
{
    protected Method $method = Method::GET;

    public function __construct(protected string $draftId) {}

    public function resolveEndpoint(): string
    {
        return "/v1/draft/{$this->draftId}/picks";
    }
}
