<?php

namespace App\Actions\Chat;

use App\Models\Chat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OPGG\LaravelMcpServer\Services\ToolService\ToolInterface;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Prism;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage;
use Prism\Relay\Facades\Relay;

class StreamChatAction
{
    public function __construct(private readonly GenerateChatTitleAction $generateChatTitle) {}

    public function __invoke(Request $request, ?Chat $chat = null)
    {
        return response()->stream(function () use ($request, $chat) {
            $messages = $request->input('messages', []);

            if (empty($messages)) {
                return;
            }

            if ($chat) {
                foreach ($messages as $message) {
                    if (! isset($message['id'])) {
                        $chat->messages()->create([
                            'type' => $message['type'] ?? ($message['role'] === 'user' ? 'prompt' : 'response'),
                            'role' => $message['role'] ?? ($message['type'] === 'prompt' ? 'user' : 'assistant'),
                            'name' => $message['name'] ?? null,
                            'call_id' => $message['call_id'] ?? null,
                            'content' => $message['content'] ?? '',
                            'content_json' => $message['content_json'] ?? null,
                        ]);
                    }
                }
            }

            // Build a working conversation array we can extend across tool loops
            $conversation = collect($messages)
                ->map(function ($message) {
                    return [
                        'role' => $message['role'] ?? ($message['type'] === 'prompt' ? 'user' : 'assistant'),
                        'content' => (string) ($message['content'] ?? ''),
                    ];
                })
                ->values()
                ->all();

            $turns = 0;
            $maxTurns = 10;
            $anyAssistantOutput = false;
            $thinkingBufferAll = '';

            while ($turns < $maxTurns) {
                $turns++;

                $prismMessages = collect($conversation)
                    ->map(function ($m) {
                        return $m['role'] === 'user' ? new UserMessage($m['content']) : new AssistantMessage($m['content']);
                    })
                    ->toArray();

                $fullResponse = '';
                $thinkingBuffer = '';
                $toolCallBuffers = [];

                try {
                    $stream = Prism::text()
                        ->using(Provider::OpenAI, 'gpt-5-mini')
                        ->withMessages($prismMessages)
                        ->withTools(Relay::tools('fantasy-football-mcp'))
                        ->asStream();

                    foreach ($stream as $chunk) {
                        $piece = $chunk->text ?? '';
                        if ($piece !== '') {
                            $anyAssistantOutput = true;
                            $fullResponse += '';
                            $fullResponse .= $piece;
                            echo $piece;
                            ob_flush();
                            flush();
                        }

                        // Capture thinking and tool calls
                        try {
                            $arr = json_decode(json_encode($chunk), true) ?? [];
                            $thinkingDelta = $arr['thinking'] ?? $arr['reasoning'] ?? ($arr['metadata']['thinking'] ?? null);
                            if (is_string($thinkingDelta) && $thinkingDelta !== '') {
                                $thinkingBuffer .= $thinkingDelta;
                            }
                            $toolCalls = $arr['tool_calls'] ?? $arr['toolCalls'] ?? null;
                            if (is_array($toolCalls)) {
                                foreach ($toolCalls as $tc) {
                                    $id = $tc['id'] ?? ($tc['call_id'] ?? null);
                                    $name = $tc['function']['name'] ?? ($tc['name'] ?? null);
                                    $argsDelta = $tc['function']['arguments'] ?? ($tc['arguments'] ?? null);
                                    if ($id) {
                                        $buffer = $toolCallBuffers[$id] ?? ['id' => $id, 'name' => $name, 'arguments' => ''];
                                        if (is_string($argsDelta)) {
                                            $buffer['arguments'] .= $argsDelta;
                                        } elseif (is_array($argsDelta)) {
                                            $buffer['arguments'] = json_encode($argsDelta);
                                        }
                                        if ($name && empty($buffer['name'])) {
                                            $buffer['name'] = $name;
                                        }
                                        $toolCallBuffers[$id] = $buffer;
                                    }
                                }
                            }
                        } catch (\Throwable $ignored) {
                        }
                    }
                } catch (\Exception $e) {
                    Log::error('Stream error', ['exception' => $e->getMessage()]);
                    echo 'Error: Unable to generate response.';
                    ob_flush();
                    flush();
                    break;
                }

                // Persist thinking for this turn
                if ($chat && $thinkingBuffer !== '') {
                    $thinkingBufferAll .= $thinkingBuffer;
                    $chat->messages()->create([
                        'type' => 'response',
                        'role' => 'thinking',
                        'content' => $thinkingBuffer,
                    ]);
                }

                // If no tool calls were emitted, persist assistant text and finish
                if (empty($toolCallBuffers)) {
                    if ($chat && $fullResponse !== '') {
                        $chat->messages()->create([
                            'type' => 'response',
                            'role' => 'assistant',
                            'content' => $fullResponse,
                        ]);
                        if ($chat->title === 'Untitled') {
                            ($this->generateChatTitle)($chat);
                        }
                    }
                    break;
                }

                // We have tool calls; execute them locally and append results, then continue loop
                $toolResultsSummary = [];
                foreach ($toolCallBuffers as $call) {
                    $argsText = (string) ($call['arguments'] ?? '');
                    $args = [];
                    $decoded = json_decode($argsText, true);
                    if (is_array($decoded)) {
                        $args = $decoded;
                    }

                    // Persist assistant tool call record
                    if ($chat) {
                        $chat->messages()->create([
                            'type' => 'response',
                            'role' => 'assistant',
                            'name' => $call['name'] ?? null,
                            'call_id' => $call['id'] ?? null,
                            'content' => $argsText,
                            'content_json' => $args ?: null,
                        ]);
                    }

                    // Execute tool by name (local registry)
                    $result = null;
                    try {
                        $result = $this->executeToolByName((string) ($call['name'] ?? ''), $args);
                    } catch (\Throwable $ex) {
                        $result = ['error' => 'Tool execution failed', 'message' => $ex->getMessage()];
                    }

                    if ($chat) {
                        $chat->messages()->create([
                            'type' => 'response',
                            'role' => 'tool',
                            'name' => $call['name'] ?? null,
                            'call_id' => $call['id'] ?? null,
                            'content' => is_string($result) ? $result : json_encode($result, JSON_UNESCAPED_SLASHES),
                            'content_json' => is_array($result) ? $result : null,
                        ]);
                    }

                    $toolResultsSummary[] = [
                        'call_id' => $call['id'] ?? null,
                        'name' => $call['name'] ?? null,
                        'result' => $result,
                    ];
                }

                // Build a follow-up user message to feed tool results back into the model
                $conversation[] = [
                    'role' => 'user',
                    'content' => "Tool results available:\n".json_encode($toolResultsSummary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\nPlease continue your response using these results.",
                ];
            }
        }, 200, [
            'Cache-Control' => 'no-cache',
            'Content-Type' => 'text/event-stream',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function executeToolByName(string $toolName, array $args): mixed
    {
        $normalized = $this->normalizeToolName($toolName);
        foreach (config('mcp-server.tools', []) as $class) {
            if (class_exists($class)) {
                $instance = app($class);
                if ($instance instanceof ToolInterface && $instance->name() === $normalized) {
                    return $instance->execute($args);
                }
            }
        }

        throw new \RuntimeException("Tool not found: {$toolName}");
    }

    private function normalizeToolName(string $raw): string
    {
        // Relay prefixed pattern: relay__{server}__{tool}
        if (str_contains($raw, '__')) {
            $parts = explode('__', $raw);
            $raw = end($parts) ?: $raw;
        }
        // In case other separators are used (e.g., dots)
        if (str_contains($raw, '.')) {
            $chunks = explode('.', $raw);
            $raw = end($chunks) ?: $raw;
        }

        return trim($raw);
    }
}
