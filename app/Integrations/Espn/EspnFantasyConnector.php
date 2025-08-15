<?php

namespace App\Integrations\Espn;

use Saloon\Http\Connector;

class EspnFantasyConnector extends Connector
{
    public function resolveBaseUrl(): string
    {
        return 'https://lm-api-reads.fantasy.espn.com';
    }

    protected function defaultHeaders(): array
    {
        return [
            'Accept' => 'application/json',
        ];
    }

    protected function defaultConfig(): array
    {
        return [
            'timeout' => 15.0,
        ];
    }
}
