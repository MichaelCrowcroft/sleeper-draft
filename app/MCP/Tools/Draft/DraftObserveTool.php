<?php

namespace App\MCP\Tools\Draft;

use App\Services\SleeperSdk;
use Illuminate\Support\Facades\App as LaravelApp;
use OPGG\LaravelMcpServer\Services\ToolService\ToolInterface;

class DraftObserveTool implements ToolInterface
{
    public function name(): string
    {
        return 'draft_observe';
    }

    public function description(): string
    {
        return 'Fetch current draft picks to update a live draft board.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['draft_id'],
            'properties' => [
                'draft_id' => ['type' => 'string'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'default' => 1000],
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
        $limit = (int) ($arguments['limit'] ?? 1000);

        return ['picks' => array_slice($picks, 0, $limit)];
    }
}
