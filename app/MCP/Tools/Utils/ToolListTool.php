<?php

namespace App\MCP\Tools\Utils;

use Illuminate\Support\Facades\Config;
use OPGG\LaravelMcpServer\Services\ToolService\ToolInterface;

class ToolListTool implements ToolInterface
{
    public function name(): string
    {
        return 'tool_list';
    }

    public function description(): string
    {
        return 'Return available tools and short descriptions.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                '_dummy' => ['type' => 'string', 'description' => 'Unused parameter for Anthropic compatibility'],
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
        $tools = [];
        foreach (Config::get('mcp-server.tools', []) as $class) {
            if (class_exists($class)) {
                $instance = app($class);
                if ($instance instanceof ToolInterface) {
                    $tools[] = [
                        'name' => $instance->name(),
                        'streaming' => method_exists($instance, 'isStreaming') ? (bool) $instance->isStreaming() : false,
                        'description' => $instance->description(),
                        'input_schema' => $instance->inputSchema(),
                    ];
                }
            }
        }

        return ['tools' => $tools];
    }
}
