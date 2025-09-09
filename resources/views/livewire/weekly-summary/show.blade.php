<?php

use App\Actions\Matchups\AssembleMatchupViewModel;
use App\Actions\Sleeper\FetchLeague;
use App\Models\WeeklySummary;
use Livewire\Volt\Component;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Prism;
use Prism\Prism\ValueObjects\ProviderTool;
use Prism\Relay\Facades\Relay;

new class extends Component {
    public string $leagueId;
    public int $year;
    public int $week;
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
    public ?WeeklySummary $weeklySummary = null;

    public function mount(string $leagueId, int $year, int $week): void
    {
        $this->leagueId = $leagueId;
        $this->year = $year;
        $this->week = $week;

        // Load existing summary if available
        $this->weeklySummary = WeeklySummary::forLeagueWeek($leagueId, $year, $week)->first();
    }

    public function getLeagueProperty(): array
    {
        return app(FetchLeague::class)->execute($this->leagueId);
    }

    public function generateWeeklySummary(): void
    {
        if ($this->isRunning) {
            return;
        }

        $this->reset(['output', 'answerStreaming', 'finalAnswer', 'error', 'steps', 'currentStep', 'isCompleted']);
        $this->isRunning = true;
        $this->status = 'Starting weekly summary generation...';

        // Initial streamed messages
        $this->stream(to: 'output', content: "🏈 Starting Fantasy Football Weekly Summary Generation...\n\n");
        $this->stream(to: 'status', content: 'Initializing Prism...');

        $this->executeWeeklySummaryGeneration();
    }

    private function executeWeeklySummaryGeneration(): void
    {
        try {
            $this->stream(to: 'output', content: "🔧 Configuring Prism with Groq provider...\n");
            $this->stream(to: 'status', content: 'Configuring Prism...');

            // Log the start of execution
            $this->stream(to: 'output', content: "📡 Provider: Groq (openai/gpt-oss-120b)\n");
            $this->stream(to: 'output', content: "🎯 Tools: Browser Search + Sleeper Draft MCP\n");
            $this->stream(to: 'output', content: "📝 Task: Weekly League Summary Generation\n\n");
            $this->stream(to: 'output', content: "=" . str_repeat("=", 50) . "\n");
            $this->stream(to: 'output', content: "PRISM EXECUTION STARTED\n");
            $this->stream(to: 'output', content: "=" . str_repeat("=", 50) . "\n\n");

            $this->stream(to: 'status', content: 'Fetching matchup data...');

            // Fetch matchup data for the week
            $matchupData = $this->getWeeklyMatchupData();
            $this->stream(to: 'output', content: "✅ Fetched matchup data for Week {$this->week}\n");

            // Generate the prompt with matchup data
            $prompt = $this->buildWeeklySummaryPrompt($matchupData);
            $this->stream(to: 'output', content: "📝 Generated weekly summary prompt\n\n");

            $this->stream(to: 'status', content: 'Executing Prism request...');

            $generator = Prism::text()
                ->using(Provider::Groq, 'openai/gpt-oss-120b')
                ->withProviderTools([
                    new ProviderTool(type: 'browser_search')
                ])
                ->withProviderOptions([
                    'reasoning' => ['effort' => 'high']
                ])
                ->withTools(Relay::tools('sleeperdraft'))
                ->withPrompt($prompt)
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

            // Save the generated summary
            $this->saveWeeklySummary();

        } catch (\Exception $e) {
            $this->error = $e->getMessage();
            $this->isRunning = false;
            $this->stream(to: 'status', content: 'Error occurred');
        }
    }

    private function getWeeklyMatchupData(): array
    {
        // Get all matchups for the week
        $matchups = [];

        try {
            // Fetch all matchups for this league and week
            $fetchMatchups = app(\App\Actions\Sleeper\FetchMatchups::class);
            $matchupData = $fetchMatchups->execute($this->leagueId, $this->week);

            // Group by matchup_id to get pairs
            $byMatchupId = [];
            foreach ($matchupData as $matchup) {
                $matchupId = $matchup['matchup_id'] ?? null;
                if ($matchupId) {
                    $byMatchupId[$matchupId][] = $matchup;
                }
            }

            // Process each matchup pair
            foreach ($byMatchupId as $matchupId => $pair) {
                if (count($pair) >= 2) {
                    $matchups[] = [
                        'matchup_id' => $matchupId,
                        'home' => $pair[0],
                        'away' => $pair[1],
                    ];
                }
            }

            // If no complete pairs found, try to get individual matchups
            if (empty($matchups) && !empty($matchupData)) {
                // Group by roster_id for individual processing
                $rosterMatchups = [];
                foreach ($matchupData as $matchup) {
                    $rosterId = $matchup['roster_id'] ?? null;
                    if ($rosterId) {
                        $rosterMatchups[$rosterId] = $matchup;
                    }
                }
                $matchups = array_values($rosterMatchups);
            }

        } catch (\Exception $e) {
            $this->stream(to: 'output', content: "⚠️ Warning: Could not fetch matchup data: {$e->getMessage()}\n");
        }

        return $matchups;
    }

    private function buildWeeklySummaryPrompt(array $matchups): string
    {
        $league = $this->league;

        $leagueName = $league['name'] ?? 'Unknown';
        $prompt = "You are a Fantasy Football League Commissioner and analyst. Generate a comprehensive weekly summary for League '{$leagueName}' for Week {$this->week} of {$this->year}.\n\n";

        $prompt .= "LEAGUE INFORMATION:\n";
        $prompt .= "- League Name: {$leagueName}\n";
        $prompt .= "- League ID: {$this->leagueId}\n";
        $prompt .= "- Week: {$this->week}\n";
        $prompt .= "- Year: {$this->year}\n\n";

        if (!empty($matchups)) {
            $prompt .= "MATCHUP DATA:\n";
            foreach ($matchups as $i => $matchup) {
                $prompt .= "Matchup " . ($i + 1) . ":\n";

                if (isset($matchup['home']) && isset($matchup['away'])) {
                    // Complete matchup pair
                    $homePoints = $matchup['home']['points'] ?? 0;
                    $awayPoints = $matchup['away']['points'] ?? 0;
                    $homeRosterId = $matchup['home']['roster_id'] ?? 'Unknown';
                    $awayRosterId = $matchup['away']['roster_id'] ?? 'Unknown';

                    $prompt .= "  Home Team (Roster {$homeRosterId}): {$homePoints} points\n";
                    $prompt .= "  Away Team (Roster {$awayRosterId}): {$awayPoints} points\n";

                    if ($homePoints > $awayPoints) {
                        $prompt .= "  Result: Home team wins by " . number_format($homePoints - $awayPoints, 1) . " points\n";
                    } elseif ($awayPoints > $homePoints) {
                        $prompt .= "  Result: Away team wins by " . number_format($awayPoints - $homePoints, 1) . " points\n";
                    } else {
                        $prompt .= "  Result: Tie game\n";
                    }
                } else {
                    // Individual matchup data
                    $points = $matchup['points'] ?? 0;
                    $rosterId = $matchup['roster_id'] ?? 'Unknown';
                    $prompt .= "  Roster {$rosterId}: {$points} points\n";
                }

                // Add starters if available
                if (isset($matchup['starters'])) {
                    $starters = is_array($matchup['starters']) ? $matchup['starters'] : [];
                    $prompt .= "  Starters: " . implode(', ', $starters) . "\n";
                }

                $prompt .= "\n";
            }
        } else {
            $prompt .= "No matchup data available for this week.\n\n";
        }

        $prompt .= "INSTRUCTIONS:\n";
        $prompt .= "Generate a comprehensive weekly summary that includes:\n";
        $prompt .= "1. Overall league performance overview\n";
        $prompt .= "2. Standout performers and their key contributions\n";
        $prompt .= "3. Notable upsets, close games, and blowouts\n";
        $prompt .= "4. League trends and patterns emerging\n";
        $prompt .= "5. Predictions for upcoming weeks based on current form\n";
        $prompt .= "6. Any unusual or interesting matchup outcomes\n";
        $prompt .= "7. Team rankings based on this week's performance\n\n";

        $prompt .= "Use the available tools to gather additional context about players, teams, and current NFL news that might be relevant to this week's fantasy performance.\n\n";

        $prompt .= "Never show IDs to represent players, teams, or anything else. For rosters refer to the owner name. Format your response as a well-structured, engaging narrative that league members will enjoy reading. Use markdown formatting for better readability.\n";

        return $prompt;
    }

    private function saveWeeklySummary(): void
    {
        if (!$this->weeklySummary) {
            $this->weeklySummary = WeeklySummary::getOrCreate($this->leagueId, $this->year, $this->week);
        }

        $prompt = "Weekly Summary for League {$this->leagueId}, Week {$this->week}, Year {$this->year}";
        $this->weeklySummary->markGenerated($this->finalAnswer, $prompt);
    }

    public function clearOutput(): void
    {
        $this->reset(['output', 'error', 'steps', 'currentStep', 'isCompleted', 'status']);
    }

    public function updatedOutput(): void
    {
        $this->dispatch('scroll-to-bottom');
    }
}; ?>

<div class="max-w-6xl mx-auto p-6 md:p-8 space-y-6">
    <!-- Header -->
    <div class="bg-gradient-to-r from-emerald-50 to-green-50 dark:from-emerald-900/20 dark:to-green-900/20 rounded-lg p-6">
        <flux:heading size="xl" class="text-emerald-800 dark:text-emerald-200 mb-2">
            🏈 Weekly League Summary
        </flux:heading>
        <p class="text-emerald-700 dark:text-emerald-300">
            AI-generated comprehensive summary for {{ $this->league['name'] ?? 'League' }} • Week {{ $this->week }} • {{ $this->year }}
        </p>
    </div>

    <!-- Summary Display -->
    @if($weeklySummary && $weeklySummary->content)
        <div class="rounded-2xl border border-zinc-200/70 dark:border-zinc-700/70 bg-white dark:bg-zinc-900 shadow-sm">
            <div class="p-5 md:p-6 border-b border-zinc-200/60 dark:border-zinc-700/60">
                <div class="flex items-center justify-between">
                    <div>
                        <flux:heading size="md" class="mb-1">Weekly Summary</flux:heading>
                        <p class="text-sm text-muted-foreground">
                            Generated {{ $weeklySummary->generated_at?->diffForHumans() }}
                            @if($weeklySummary->isRecent())
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 ml-2">
                                    Recent
                                </span>
                            @endif
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <flux:button
                            wire:click="generateWeeklySummary"
                            variant="outline"
                            size="sm"
                            :disabled="$isRunning"
                        >
                            <flux:icon name="arrow-path" class="w-4 h-4 mr-2" />
                            Regenerate
                        </flux:button>
                    </div>
                </div>
            </div>
            <div class="p-5 md:p-6">
                <div class="prose prose-zinc dark:prose-invert max-w-none">
                    {!! \Illuminate\Support\Str::of($weeklySummary->content)->markdown() !!}
                </div>
            </div>
        </div>
    @else
        <!-- Generation Interface -->
        <div class="rounded-2xl border border-zinc-200/70 dark:border-zinc-700/70 bg-white dark:bg-zinc-900 shadow-sm">
            <div class="p-5 md:p-6 border-b border-zinc-200/60 dark:border-zinc-700/60">
                <div class="flex items-center justify-between">
                    <div class="flex-1">
                        <flux:heading size="md" class="mb-2">Generate Weekly Summary</flux:heading>
                        <p class="text-sm text-muted-foreground">
                            Create an AI-powered summary of this week's league performance
                        </p>
                    </div>
                    <div class="flex flex-col gap-2 w-40 shrink-0">
                        <flux:button
                            wire:click="generateWeeklySummary"
                            variant="primary"
                            :disabled="$isRunning"
                            wire:loading.attr="disabled"
                            wire:target="generateWeeklySummary"
                        >
                            <div wire:loading.remove wire:target="generateWeeklySummary" class="flex items-center gap-2">
                                <flux:icon name="sparkles" class="w-4 h-4" />
                                Generate
                            </div>
                            <div wire:loading wire:target="generateWeeklySummary" class="flex items-center gap-2">
                                <flux:icon name="arrow-path" class="w-4 h-4 animate-spin" />
                                Generating…
                            </div>
                        </flux:button>

                        @if($weeklySummary && $weeklySummary->content)
                            <flux:button
                                wire:click="clearOutput"
                                variant="outline"
                                size="sm"
                            >
                                <flux:icon name="trash" class="w-4 h-4" />
                                Clear
                            </flux:button>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Streaming output area -->
            <div class="p-0">
                <div class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    <!-- Assistant streaming bubble -->
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
                                                Generating weekly summary…
                                            </div>
                                        @elseif(!$answerStreaming)
                                            <div class="text-xs text-zinc-500">Awaiting generation…</div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Activity footer -->
    <div class="flex items-center justify-between text-xs text-zinc-500">
        <div class="inline-flex items-center gap-2">
            @if($isRunning)
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                <span>Generating weekly summary…</span>
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

    <!-- Debug stream (collapsible) -->
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
                <li>Fetches all matchup results for the specified week</li>
                <li>Analyzes team performances and player contributions</li>
                <li>Generates a comprehensive narrative summary using AI</li>
                <li>Incorporates current NFL news and trends for context</li>
                <li>Provides insights on league standings and upcoming matchups</li>
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
