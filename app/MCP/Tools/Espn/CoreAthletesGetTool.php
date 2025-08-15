<?php

namespace App\MCP\Tools\Espn;

use App\MCP\Tools\BaseTool;
use App\Services\EspnSdk;
use Illuminate\Support\Facades\App as LaravelApp;

class CoreAthletesGetTool extends BaseTool
{
    public function name(): string
    {
        return 'espn_core_athletes_get';
    }

    public function description(): string
    {
        return 'Get athletes from ESPN Core API (sports.core.api.espn.com).';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 20000, 'default' => 20000],
            ],
            'additionalProperties' => false,
        ];
    }

    public function annotations(): array
    {
        return [];
    }

    public function execute(array $arguments): mixed
    {
        /** @var EspnSdk $sdk */
        $sdk = LaravelApp::make(EspnSdk::class);
        $page = (int) ($arguments['page'] ?? 1);
        $limit = (int) ($arguments['limit'] ?? 20000);

        $data = $sdk->getCoreAthletes($page, $limit);

        return ['page' => $page, 'limit' => $limit, 'athletes' => $data];
    }
}
