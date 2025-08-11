<?php

namespace App\MCP\Tools\Sleeper;

use App\Services\SleeperSdk;
use Illuminate\Support\Facades\App as LaravelApp;
use OPGG\LaravelMcpServer\Services\ToolService\ToolInterface;

class DraftPicksTool implements ToolInterface
{
    public function name(): string
    {
        return 'draft.picks';
    }

    public function description(): string
    {
        return 'Get picks for a draft.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['draft_id'],
            'properties' => [
                'draft_id' => ['type' => 'string'],
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
        /** @var SleeperSdk $sdk */
        $sdk = LaravelApp::make(SleeperSdk::class);
        $picks = $sdk->getDraftPicks($arguments['draft_id']);

        return [ 'picks' => $picks ];
    }
}
