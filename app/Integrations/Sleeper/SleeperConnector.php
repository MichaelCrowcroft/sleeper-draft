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

    protected function createResponse($psrResponse, $pendingRequest): Response
    {
        $response = parent::createResponse($psrResponse, $pendingRequest);
        // Ensure JSON decoding returns array, not null, for empty bodies
        if ($response->header('Content-Type') && str_contains(strtolower($response->header('Content-Type')), 'application/json')) {
            // decode once to populate decodedJson; fallback to empty array on null
            $json = $response->json();
            if ($json === null) {
                // Hack: set decodedJson to [] to avoid null typed property issues
                $ref = new \ReflectionClass($response);
                if ($ref->hasProperty('decodedJson')) {
                    $prop = $ref->getProperty('decodedJson');
                    $prop->setAccessible(true);
                    $prop->setValue($response, []);
                }
            }
        }

        return $response;
    }
}
