<?php

namespace App\Integrations\Espn;

use Saloon\Http\Connector;

class EspnCoreConnector extends Connector
{
    public function resolveBaseUrl(): string
    {
        return 'https://sports.core.api.espn.com';
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
            'timeout' => 10.0,
        ];
    }
}
