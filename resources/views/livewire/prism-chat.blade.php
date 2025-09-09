<?php

use Illuminate\Support\Facades\Log;
use Livewire\Volt\Component;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Prism;
use Prism\Prism\ValueObjects\ProviderTool;
use Prism\Relay\Facades\Relay;

new class extends Component {
    public string $prompt = '';
    public string $output = '';
    public bool $isRunning = false;
    public bool $isCompleted = false;
    public string $error = '';
    public array $steps = [];
    public int $currentStep = 0;
    public string $status = '';

    public function mount(): void
    {
        $this->prompt = 'You are the commissioner of a Sleeper Fantasy Football league. You are producing a summary of the week that has just been to share who the winners and losers are. You want to make sure this update highlights the big boom and bust players, and any upsets. Make it hype for the league. Validate your response with search. Look up CoachCanCrusher to find the league, Week: 1, Season: 2025 Use the MCP multiple times to get information';
    }

    public function generateSummary(): void
    {
        if ($this->isRunning) {
            return;
        }

        $this->reset(['output', 'error', 'steps', 'currentStep', 'isCompleted']);
        $this->isRunning = true;
        $this->status = 'Starting Prism generation...';

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

        $response = Prism::text()
            ->using(Provider::Groq, 'openai/gpt-oss-120b')
            ->withProviderTools([
                new ProviderTool(type: 'browser_search')
            ])
            ->withTools(Relay::tools('sleeperdraft'))
            ->withPrompt($this->prompt)
            ->withMaxSteps(50)
            ->asText();

        $this->stream(to: 'status', content: 'Processing response...');

        // Stream the final response
        $this->stream(to: 'output', content: "\n🎯 FINAL RESULT:\n");
        $this->stream(to: 'output', content: str_repeat("-", 50) . "\n\n");
        $this->stream(to: 'output', content: $response->text);
        $this->stream(to: 'output', content: "\n\n" . str_repeat("-", 50) . "\n");
        $this->stream(to: 'output', content: "✅ Generation completed successfully!\n");

        $this->isRunning = false;
        $this->isCompleted = true;
        $this->stream(to: 'status', content: 'Completed');

        Log::info('Prism streaming completed', [
            'response_length' => strlen($response->text),
        ]);
    }

    public function clearOutput(): void
    {
        $this->reset(['output', 'error', 'steps', 'currentStep', 'isCompleted', 'status']);
    }

    public function updatePrompt(): void
    {
        // Just update the prompt, no action needed
    }
}; ?>

<div class="max-w-6xl mx-auto p-6 space-y-6">
    <!-- Header -->
    <div class="bg-gradient-to-r from-emerald-50 to-green-50 dark:from-emerald-900/20 dark:to-green-900/20 rounded-lg p-6">
        <flux:heading size="xl" class="text-emerald-800 dark:text-emerald-200 mb-2">
            🏈 Fantasy Football Commissioner Chat
        </flux:heading>
        <p class="text-emerald-700 dark:text-emerald-300">
            Generate AI-powered league summaries with real-time streaming output
        </p>
    </div>

    <!-- Prompt Configuration -->
    <div class="bg-white dark:bg-zinc-900 rounded-lg border border-zinc-200 dark:border-zinc-700 p-6">
        <flux:heading size="lg" class="mb-4">Configure Your Request</flux:heading>

        <flux:field>
            <flux:label>Prompt</flux:label>
            <flux:textarea
                wire:model.live="prompt"
                rows="4"
                placeholder="Enter your custom prompt or use the default fantasy football commissioner prompt..."
            />
        </flux:field>

        <div class="mt-4 flex gap-3">
            <flux:button
                wire:click="generateSummary"
                variant="primary"
                :disabled="$isRunning"
                wire:loading.attr="disabled"
                wire:target="generateSummary"
            >
                <div wire:loading.remove wire:target="generateSummary" class="flex items-center gap-2">
                    <flux:icon name="play" class="w-4 h-4" />
                    Generate Summary
                </div>
                <div wire:loading wire:target="generateSummary" class="flex items-center gap-2">
                    <flux:icon name="arrow-path" class="w-4 h-4 animate-spin" />
                    Generating...
                </div>
            </flux:button>

            <flux:button
                wire:click="clearOutput"
                variant="outline"
                :disabled="$isRunning"
            >
                <flux:icon name="trash" class="w-4 h-4" />
                Clear Output
            </flux:button>
        </div>
    </div>

    <!-- Status Bar -->
    @if($status || $isRunning)
        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg p-4">
            <div class="flex items-center gap-3">
                @if($isRunning)
                    <flux:icon name="arrow-path" class="w-5 h-5 text-blue-600 animate-spin" />
                @elseif($isCompleted)
                    <flux:icon name="check-circle" class="w-5 h-5 text-emerald-600" />
                @elseif($error)
                    <flux:icon name="exclamation-circle" class="w-5 h-5 text-red-600" />
                @endif

                <div class="flex-1">
                    <div class="font-medium text-blue-900 dark:text-blue-100">
                        Status: <span wire:stream="status">{{ $status }}</span>
                    </div>
                    @if($isRunning)
                        <div class="text-sm text-blue-600 dark:text-blue-300 mt-1">
                            Processing your request with AI tools and data sources...
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <!-- Error Display -->
    @if($error)
        <flux:callout variant="danger">
            <div class="flex items-start gap-3">
                <flux:icon name="exclamation-triangle" class="w-5 h-5 text-red-600 flex-shrink-0 mt-0.5" />
                <div>
                    <div class="font-medium">Error occurred during generation</div>
                    <div class="mt-1 text-sm text-red-600 dark:text-red-400" wire:stream="error">
                        {{ $error }}
                    </div>
                </div>
            </div>
        </flux:callout>
    @endif

    <!-- Streaming Output -->
    <div class="bg-zinc-900 text-zinc-100 rounded-lg overflow-hidden">
        <div class="flex items-center justify-between p-4 bg-zinc-800 border-b border-zinc-700">
            <div class="flex items-center gap-2">
                <flux:icon name="computer-desktop" class="w-4 h-4" />
                <span class="font-medium">Live Output Stream</span>
            </div>
            <div class="flex items-center gap-2">
                @if($isRunning)
                    <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                    <span class="text-sm text-zinc-400">Streaming...</span>
                @elseif($isCompleted)
                    <div class="w-2 h-2 bg-emerald-500 rounded-full"></div>
                    <span class="text-sm text-zinc-400">Complete</span>
                @else
                    <div class="w-2 h-2 bg-zinc-500 rounded-full"></div>
                    <span class="text-sm text-zinc-400">Ready</span>
                @endif
            </div>
        </div>

        <div class="p-6 font-mono text-sm leading-relaxed min-h-[400px] max-h-[600px] overflow-y-auto">
            @if($output)
                <pre wire:stream="output" class="whitespace-pre-wrap break-words">{{ $output }}</pre>
            @else
                <div class="text-zinc-400 italic">
                    Output will appear here when generation starts...
                </div>
            @endif
        </div>
    </div>

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