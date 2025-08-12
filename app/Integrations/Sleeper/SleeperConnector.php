<?php

namespace App\Integrations\Sleeper;

use Saloon\Http\Connector;
use Saloon\Http\Response;

class SleeperConnector extends Connector
{
    public function resolveBaseUrl(): string
    {
        return 'https://api.sleeper.app';
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

    // Use default response handling; callers must safely parse
}
