<?php

namespace App\MCP\Tools\Utils;

use OPGG\LaravelMcpServer\Services\ToolService\ToolInterface;

class ToolSchemaTool implements ToolInterface
{
    public function name(): string
    {
        return 'tool_schema';
    }

    public function description(): string
    {
        return 'Return the JSON schema of a tool for debugging.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['tool_name'],
            'properties' => [
                'tool_name' => ['type' => 'string'],
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
        $name = (string) $arguments['tool_name'];
        foreach (config('mcp-server.tools', []) as $class) {
            if (class_exists($class)) {
                $instance = app($class);
                if ($instance instanceof ToolInterface && $instance->name() === $name) {
                    return [
                        'name' => $instance->name(),
                        'input_schema' => $instance->inputSchema(),
                        'description' => $instance->description(),
                        'streaming' => method_exists($instance, 'isStreaming') ? (bool) $instance->isStreaming() : false,
                    ];
                }
            }
        }
        return ['error' => 'Tool not found'];
    }
}
