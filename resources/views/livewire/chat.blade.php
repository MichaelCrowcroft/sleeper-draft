<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Prism;
use Prism\Prism\ValueObjects\ProviderTool;
use Prism\Relay\Facades\Relay;

new class extends Component {
    public string $prompt = '';
    public array $messages = [];
    public bool $isTyping = false;

    public function sendMessage(): void
    {
        if (empty(trim($this->prompt)) || $this->isTyping) {
            return;
        }

        // Add user message to chat
        $this->messages[] = [
            'role' => 'user',
            'content' => $this->prompt,
            'timestamp' => now()
        ];

        $userPrompt = $this->prompt;
        $this->prompt = '';
        $this->isTyping = true;

        // Start AI response
        $this->messages[] = [
            'role' => 'assistant',
            'content' => '',
            'timestamp' => now(),
            'isStreaming' => true
        ];

        $this->streamResponse($userPrompt);
    }

    private function streamResponse(string $prompt): void
    {
        try {
            $generator = Prism::text()
                ->using(Provider::Groq, 'openai/gpt-oss-120b')
                ->withProviderTools([
                    new ProviderTool(type: 'browser_search')
                ])
                ->withProviderOptions([
                    'reasoning' => ['effort' => 'high']
                ])
                ->withTools(Relay::tools('sleeperdraft'))
                ->withSystemPrompt('You are a helpful assistant that can answer questions about fantasy football with the tools provided. The current user has the following Sleeper league: ' . Auth::user()->sleeper_user_id)
                ->withPrompt($prompt)
                ->withMaxSteps(50)
                ->asStream();

            $responseContent = '';

            foreach ($generator as $chunk) {
                if (!empty($chunk->text)) {
                    $responseContent .= $chunk->text;

                    // Update the last message (AI response) with streaming content
                    $this->messages[count($this->messages) - 1]['content'] = $responseContent;
                    $this->dispatch('scroll-to-bottom');
                }
            }

            // Mark as completed
            $this->messages[count($this->messages) - 1]['isStreaming'] = false;

        } catch (\Exception $e) {
            // Handle error
            $this->messages[count($this->messages) - 1]['content'] = 'Sorry, I encountered an error: ' . $e->getMessage();
            $this->messages[count($this->messages) - 1]['isStreaming'] = false;
        }

        $this->isTyping = false;
        $this->dispatch('scroll-to-bottom');
    }

    public function clearChat(): void
    {
        $this->messages = [];
        $this->prompt = '';
    }
}; ?>

<div class="flex flex-col h-screen bg-white dark:bg-gray-900">
    <!-- Header -->
    <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 bg-emerald-500 rounded-full flex items-center justify-center">
                <span class="text-white font-bold text-sm">AI</span>
            </div>
            <div>
                <h1 class="text-lg font-semibold text-gray-900 dark:text-white">Fantasy Football Assistant</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">Powered by Prism</p>
            </div>
        </div>
        <flux:button wire:click="clearChat" variant="outline" size="sm">
            <flux:icon name="trash" class="w-4 h-4" />
            Clear Chat
        </flux:button>
    </div>

    <!-- Messages Container -->
    <div class="flex-1 overflow-y-auto p-4 space-y-4" id="messages-container">
        @if(empty($messages))
            <div class="flex items-center justify-center h-full text-center">
                <div class="max-w-md">
                    <div class="w-16 h-16 bg-emerald-100 dark:bg-emerald-900/30 rounded-full flex items-center justify-center mx-auto mb-4">
                        <flux:icon name="chat-bubble-left-right" class="w-8 h-8 text-emerald-600 dark:text-emerald-400" />
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">Welcome to Fantasy Football Assistant</h3>
                    <p class="text-gray-500 dark:text-gray-400 text-sm">
                        Ask me anything about fantasy football, player stats, matchups, or league analysis.
                    </p>
                </div>
            </div>
        @else
            @foreach($messages as $message)
                <div class="flex {{ $message['role'] === 'user' ? 'justify-end' : 'justify-start' }}">
                    <div class="max-w-3xl {{ $message['role'] === 'user' ? 'order-2' : 'order-1' }}">
                        <div class="flex items-start gap-3 {{ $message['role'] === 'user' ? 'flex-row-reverse' : 'flex-row' }}">
                            <!-- Avatar -->
                            <div class="w-8 h-8 rounded-full flex items-center justify-center shrink-0 {{ $message['role'] === 'user' ? 'bg-emerald-500' : 'bg-gray-500' }}">
                                <span class="text-white font-medium text-sm">
                                    {{ $message['role'] === 'user' ? 'U' : 'AI' }}
                                </span>
                            </div>

                            <!-- Message Content -->
                            <div class="flex-1">
                                <div class="rounded-2xl px-4 py-3 {{ $message['role'] === 'user'
                                    ? 'bg-emerald-500 text-white'
                                    : 'bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-white' }}">

                                    @if($message['role'] === 'assistant')
                                        @if($message['isStreaming'] ?? false)
                                            <div class="prose prose-sm dark:prose-invert max-w-none">
                                                {!! \Illuminate\Support\Str::of($message['content'])->markdown() !!}
                                            </div>
                                            <div class="flex items-center gap-2 mt-2 text-xs text-gray-500">
                                                <div class="flex gap-1">
                                                    <div class="w-1 h-1 bg-gray-400 rounded-full animate-bounce"></div>
                                                    <div class="w-1 h-1 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0.1s"></div>
                                                    <div class="w-1 h-1 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0.2s"></div>
                                                </div>
                                                <span>AI is typing...</span>
                                            </div>
                                        @else
                                            <div class="prose prose-sm dark:prose-invert max-w-none">
                                                {!! \Illuminate\Support\Str::of($message['content'])->markdown() !!}
                                            </div>
                                        @endif
                                    @else
                                        <div class="whitespace-pre-wrap">{{ $message['content'] }}</div>
                                    @endif
                                </div>

                                <!-- Timestamp -->
                                <div class="text-xs text-gray-500 mt-1 px-2 {{ $message['role'] === 'user' ? 'text-right' : 'text-left' }}">
                                    {{ $message['timestamp']->format('g:i A') }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        @endif
    </div>

    <!-- Input Area -->
    <div class="border-t border-gray-200 dark:border-gray-700 p-4 bg-white dark:bg-gray-900">
        <form wire:submit="sendMessage" class="flex gap-3">
            <div class="flex-1">
                <flux:textarea
                    wire:model="prompt"
                    placeholder="Ask about fantasy football..."
                    rows="1"
                    :disabled="$isTyping"
                    class="resize-none"
                    wire:keydown.enter.prevent="sendMessage"
                ></flux:textarea>
            </div>
            <flux:button
                type="submit"
                :disabled="empty(trim($prompt)) || $isTyping"
                wire:loading.attr="disabled"
                wire:target="sendMessage"
            >
                <div wire:loading.remove wire:target="sendMessage" class="flex items-center gap-2">
                    <flux:icon name="paper-airplane" class="w-4 h-4" />
                    Send
                </div>
                <div wire:loading wire:target="sendMessage" class="flex items-center gap-2">
                    <flux:icon name="arrow-path" class="w-4 h-4 animate-spin" />
                    Sending...
                </div>
            </flux:button>
        </form>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('livewire:init', () => {
    Livewire.on('scroll-to-bottom', () => {
        const container = document.getElementById('messages-container');
        if (container) {
            container.scrollTop = container.scrollHeight;
        }
    });

    // Auto-resize textarea
    document.addEventListener('input', function(e) {
        if (e.target.tagName.toLowerCase() === 'textarea') {
            e.target.style.height = 'auto';
            e.target.style.height = (e.target.scrollHeight) + 'px';
        }
    });
});
</script>
@endpush