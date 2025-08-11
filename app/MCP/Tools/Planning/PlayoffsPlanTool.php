<?php

namespace App\MCP\Tools\Planning;

use App\Services\SleeperSdk;
use Illuminate\Support\Facades\App as LaravelApp;
use OPGG\LaravelMcpServer\Services\ToolService\ToolInterface;

class PlayoffsPlanTool implements ToolInterface
{
    public function name(): string
    {
        return 'playoffs.plan';
    }

    public function description(): string
    {
        return 'Highlight playoff weeks (15–17) schedule and recommend stash/stream targets by positional SOS.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['season'],
            'properties' => [
                'sport' => ['type' => 'string', 'default' => 'nfl'],
                'season' => ['type' => 'string'],
                'weeks' => ['type' => 'array', 'items' => ['type' => 'integer'], 'default' => [15,16,17]],
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
        // Placeholder: Sleeper doesn’t expose positional SOS directly. Provide structure.
        $weeks = (array) ($arguments['weeks'] ?? [15,16,17]);
        return [
            'weeks' => $weeks,
            'notes' => 'To fully implement, integrate SOS model and schedule feed; this returns structure for downstream use.',
        ];
    }
}
