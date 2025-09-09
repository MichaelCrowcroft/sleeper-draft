<?php

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Volt\Component;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Prism;
use Prism\Prism\ValueObjects\ProviderTool;
use Prism\Relay\Facades\Relay;

new class extends Component {
    public string $prompt = '';
    public string $output = '';
    public string $answerStreaming = '';
    public string $finalAnswer = '';
    public bool $isRunning = false;
    public bool $isCompleted = false;
    public string $error = '';
    public array $steps = [];
    public int $currentStep = 0;
    public string $status = '';
    public bool $showActivity = false;

    public function mount(): void
    {
        $this->prompt = 'You are the commissioner of a Sleeper Fantasy Football league. You are producing a summary of the week that has just been to share who the winners and losers are. You want to make sure this update highlights the big boom and bust players, and any upsets. Make it hype for the league. Validate your response with search. Look up CoachCanCrusher to find the league, Week: 1, Season: 2025 Use the MCP multiple times to get information';
    }

    public function generateSummary(): void
    {
        if ($this->isRunning) {
            return;
        }

        $this->reset(['output', 'answerStreaming', 'finalAnswer', 'error', 'steps', 'currentStep', 'isCompleted']);
        $this->isRunning = true;
        $this->status = 'Starting Prism generation...';

        // Initial streamed messages
        $this->stream(to: 'output', content: "🚀 Starting Fantasy Football Weekly Summary Generation...\n\n");
        $this->stream(to: 'status', content: 'Initializing Prism...');

        try {
            $this->executePrism();
        } catch (\Throwable $e) {
            Log::error('Prism execution error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->error = $e->getMessage();
            $this->stream(to: 'error', content: $e->getMessage());
            $this->stream(to: 'status', content: 'Error occurred');
            $this->isRunning = false;
        }
    }

    private function executePrism(): void
    {
        $this->stream(to: 'output', content: "🔧 Configuring Prism with Groq provider...\n");
        $this->stream(to: 'status', content: 'Configuring Prism...');

        // Log the start of execution
        $this->stream(to: 'output', content: "📡 Provider: Groq (openai/gpt-oss-120b)\n");
        $this->stream(to: 'output', content: "🔍 Tools: Browser Search + Sleeper Draft MCP\n");
        $this->stream(to: 'output', content: "📝 Prompt: Fantasy League Commissioner Summary\n\n");
        $this->stream(to: 'output', content: "=" . str_repeat("=", 50) . "\n");
        $this->stream(to: 'output', content: "PRISM EXECUTION STARTED\n");
        $this->stream(to: 'output', content: "=" . str_repeat("=", 50) . "\n\n");

        $this->stream(to: 'status', content: 'Executing Prism request...');

        $generator = Prism::text()
            ->using(Provider::Gemini, 'gemini-2.5-flash')
            // ->using(Provider::Groq, 'openai/gpt-oss-120b')
            // ->withProviderTools([
            //     new ProviderTool(type: 'browser_search')
            // ])
            ->withTools(Relay::tools('sleeperdraft'))
            ->withPrompt($this->prompt)
            ->withMaxSteps(50)
            ->asStream();

        foreach ($generator as $chunk) {
            // Stream plain text tokens
            if (! empty($chunk->text)) {
                $this->stream(to: 'output', content: $chunk->text);
                $this->answerStreaming .= $chunk->text;
                $this->stream(to: 'answer', content: $chunk->text);
            }

            // Stream tool calls
            if (! empty($chunk->toolCalls)) {
                foreach ($chunk->toolCalls as $call) {
                    $args = '';
                    try {
                        $args = json_encode($call->arguments(), JSON_PRETTY_PRINT);
                    } catch (\Throwable $e) {
                        $args = '[unparsed arguments]';
                    }
                    $this->stream(
                        to: 'output',
                        content: "\n\n[Tool Call] {$call->name}\nArguments: {$args}\n"
                    );
                }
            }

            // Stream tool results
            if (! empty($chunk->toolResults)) {
                foreach ($chunk->toolResults as $result) {
                    // Safely encode args
                    $encodedArgs = '';
                    try {
                        $encodedArgs = json_encode($result->args, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                    } catch (\Throwable $e) {
                        $encodedArgs = '[unparsed args]';
                    }

                    // Safely encode result
                    $encodedResult = '';
                    try {
                        if (is_array($result->result)) {
                            $encodedResult = json_encode($result->result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                        } elseif (is_scalar($result->result) || is_null($result->result)) {
                            $encodedResult = (string) ($result->result ?? 'null');
                        } else {
                            $encodedResult = json_encode($result->result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                        }
                    } catch (\Throwable $e) {
                        $encodedResult = '[unparsed result]';
                    }

                    $this->stream(
                        to: 'output',
                        content: "\n[Tool Result] {$result->toolName}\nArgs: {$encodedArgs}\nResult: {$encodedResult}\n"
                    );
                }
            }
        }

        $this->stream(to: 'status', content: 'Processing response...');

        // Finalize
        $this->finalAnswer = $this->answerStreaming;
        $this->stream(to: 'output', content: "\n🎯 FINAL RESULT\n");
        $this->stream(to: 'output', content: str_repeat('-', 50) . "\n");
        $this->isRunning = false;
        $this->isCompleted = true;
        $this->stream(to: 'status', content: 'Completed');
    }

    public function clearOutput(): void
    {
        $this->reset(['output', 'error', 'steps', 'currentStep', 'isCompleted', 'status']);
    }

    public function testStreaming(): void
    {
        if ($this->isRunning) {
            return;
        }

        $this->reset(['output', 'error', 'steps', 'currentStep', 'isCompleted']);
        $this->isRunning = true;
        $this->status = 'Testing streaming...';
        $this->output = "🚀 Testing Streaming Output...\n\n";

        // Simulate streaming output with delays
        $this->output .= "Step 1: Initializing...\n";
        $this->dispatch('$refresh');
        sleep(1);

        $this->output .= "Step 2: Configuring tools...\n";
        $this->status = 'Step 2: Configuring...';
        $this->dispatch('$refresh');
        sleep(1);

        $this->output .= "Step 3: Processing data...\n";
        $this->status = 'Step 3: Processing...';
        $this->dispatch('$refresh');
        sleep(1);

        $this->output .= "Step 4: Generating response...\n";
        $this->status = 'Step 4: Generating...';
        $this->dispatch('$refresh');
        sleep(1);

        $this->output .= "\n✅ Streaming test completed!\n";
        $this->status = 'Test completed';
        $this->isRunning = false;
        $this->isCompleted = true;
        $this->dispatch('$refresh');
    }

    public function updatePrompt(): void
    {
        // Just update the prompt, no action needed
    }

    public function updatedOutput(): void
    {
        // This method is called whenever the output property is updated
        // It will trigger a UI refresh automatically
        $this->dispatch('scroll-to-bottom');
    }
}; ?>

<div class="max-w-5xl mx-auto p-6 md:p-8 space-y-6">
    <!-- Header -->
    <div class="bg-gradient-to-r from-emerald-50 to-green-50 dark:from-emerald-900/20 dark:to-green-900/20 rounded-lg p-6">
        <flux:heading size="xl" class="text-emerald-800 dark:text-emerald-200 mb-2">
            🏈 Fantasy Football Commissioner
        </flux:heading>
        <p class="text-emerald-700 dark:text-emerald-300">
            Generate AI-powered league summaries with real-time streaming output
        </p>
    </div>

    <!-- Prompt Configuration -->
    <div class="rounded-2xl border border-zinc-200/70 dark:border-zinc-700/70 bg-white dark:bg-zinc-900 shadow-sm">
        <div class="p-5 md:p-6 border-b border-zinc-200/60 dark:border-zinc-700/60">
            <div class="flex items-start gap-3">
                <div class="flex-1">
                    <flux:textarea
                        wire:model.live="prompt"
                        rows="3"
                        placeholder="Ask the AI commissioner…"/>
                </div>
                <div class="flex flex-col gap-2 w-40 shrink-0">
                    <flux:button
                        wire:click="generateSummary"
                        variant="primary"
                        :disabled="$isRunning"
                        wire:loading.attr="disabled"
                        wire:target="generateSummary"
                    >
                        <div wire:loading.remove wire:target="generateSummary" class="flex items-center gap-2">
                            <flux:icon name="paper-airplane" class="w-4 h-4" />
                            Send
                        </div>
                        <div wire:loading wire:target="generateSummary" class="flex items-center gap-2">
                            <flux:icon name="arrow-path" class="w-4 h-4 animate-spin" />
                            Sending…
                        </div>
                    </flux:button>

                    <flux:button
                        wire:click="clearOutput"
                        variant="outline"
                        :disabled="$isRunning"
                    >
                        <flux:icon name="trash" class="w-4 h-4" />
                        Clear
                    </flux:button>
                </div>
            </div>
        </div>

        <div class="p-0">
            <!-- Chat timeline -->
            <div class="divide-y divide-zinc-200 dark:divide-zinc-800">
                <!-- User message bubble -->
                @if($prompt)
                    <div class="p-5 md:p-6">
                        <div class="flex items-start gap-3">
                            <div class="h-9 w-9 shrink-0 rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300 flex items-center justify-center font-semibold">U</div>
                            <div class="max-w-none flex-1">
                                <div class="inline-block rounded-2xl bg-emerald-50 dark:bg-emerald-900/20 text-emerald-900 dark:text-emerald-100 px-4 py-3 shadow-sm">
                                    <div class="whitespace-pre-wrap break-words">{{ $prompt }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Assistant streaming bubble (always present so wire:stream can attach) -->
                <div class="p-5 md:p-6">
                    <div class="flex items-start gap-3">
                        <div class="h-9 w-9 shrink-0 rounded-full bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900 flex items-center justify-center font-semibold">AI</div>
                        <div class="max-w-none flex-1">
                            <div class="inline-block rounded-2xl bg-zinc-100 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 px-4 py-3 shadow-sm min-w-[200px]">
                                <div class="prose prose-zinc dark:prose-invert max-w-none">
                                    <div wire:stream="answer">{!! nl2br(e($answerStreaming)) !!}</div>
                                    @if($isRunning)
                                        <div class="mt-2 inline-flex items-center gap-2 text-xs text-zinc-500">
                                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                            Generating…
                                        </div>
                                    @elseif(!$answerStreaming)
                                        <div class="text-xs text-zinc-500">Awaiting response…</div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Assistant final bubble -->
                @if($isCompleted && $finalAnswer)
                    <div class="p-5 md:p-6">
                        <div class="flex items-start gap-3">
                            <div class="h-9 w-9 shrink-0 rounded-full bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900 flex items-center justify-center font-semibold">AI</div>
                            <div class="max-w-none flex-1">
                                <div class="inline-block rounded-2xl bg-zinc-100 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 px-4 py-3 shadow-sm">
                                    <div class="prose prose-zinc dark:prose-invert max-w-none">
                                        {!! \Illuminate\Support\Str::of($finalAnswer)->markdown() !!}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Activity footer -->
    <div class="flex items-center justify-between text-xs text-zinc-500">
        <div class="inline-flex items-center gap-2">
            @if($isRunning)
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                <span>Generating response…</span>
            @elseif($isCompleted)
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                <span>Completed</span>
            @elseif($error)
                <span class="h-1.5 w-1.5 rounded-full bg-red-500"></span>
                <span>Error</span>
            @endif
        </div>
        <div>
            <span wire:stream="status">{{ $status }}</span>
        </div>
    </div>

    <!-- Error Banner -->
    @if($error)
        <div class="rounded-lg bg-red-50 dark:bg-red-900/20 text-red-800 dark:text-red-200 border border-red-200 dark:border-red-800 px-4 py-3">
            <div class="font-medium">An error occurred</div>
            <div class="text-sm mt-1" wire:stream="error">{{ $error }}</div>
        </div>
    @endif

    <!-- Developer output (collapsible) -->
    <details class="rounded-lg border border-zinc-200/70 dark:border-zinc-800/70 bg-zinc-50 dark:bg-zinc-900/40">
        <summary class="cursor-pointer px-4 py-2 text-sm text-zinc-700 dark:text-zinc-300">Debug stream</summary>
        <div class="p-4 font-mono text-xs leading-relaxed overflow-x-auto">
            <pre wire:stream="output" class="whitespace-pre-wrap break-words">{{ $output }}</pre>
        </div>
    </details>

    <!-- Usage Instructions -->
    <flux:callout>
        <div class="space-y-2">
            <flux:heading size="sm">How it works:</flux:heading>
            <ul class="text-sm text-zinc-600 dark:text-zinc-400 space-y-1 list-disc list-inside">
                <li>Customize your prompt or use the default fantasy football commissioner template</li>
                <li>Click "Generate Summary" to start the AI-powered analysis</li>
                <li>Watch real-time streaming output as Prism uses tools and generates content</li>
                <li>The system will search web sources and use Sleeper Draft MCP tools for accurate data</li>
                <li>Results stream live so you can see the AI's thought process and tool usage</li>
            </ul>
        </div>
    </flux:callout>
</div>

@push('scripts')
<script>
document.addEventListener('livewire:init', () => {
    Livewire.on('scroll-to-bottom', () => {
        const outputContainer = document.querySelector('.overflow-y-auto');
        if (outputContainer) {
            outputContainer.scrollTop = outputContainer.scrollHeight;
        }
    });
});
</script>
@endpush