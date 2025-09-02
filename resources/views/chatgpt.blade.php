<x-layouts.app.marketing>
    <div class="min-h-screen bg-[#FDFDFC] dark:bg-[#0a0a0a] text-[#1b1b18] dark:text-[#EDEDEC] px-4 sm:px-6 py-8 sm:py-12 break-words overflow-x-hidden">
        <div class="mx-auto max-w-6xl w-full">
            @include('partials.marketing-nav')

            <div class="rounded-2xl border border-black/10 bg-white p-6 sm:p-8 shadow-sm dark:border-white/10 dark:bg-[#141414]">
                <div class="grid items-start gap-8 md:gap-10 md:grid-cols-2 w-full min-w-0">
                    <div class="min-w-0">
                        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4">

                            <h1 class="text-2xl leading-tight font-semibold sm:text-3xl lg:text-4xl">ChatGPT Custom GPT</h1>
                        </div>
                        <p class="mt-3 text-[15px] leading-7 text-[#575654] dark:text-[#A1A09A]">
                            Experience fantasy football like never before with our specialized Custom GPT that seamlessly integrates with the MCP Tools for Fantasy Football. Get intelligent analysis, draft recommendations, and strategic insights powered by real-time data.
                        </p>
                        <div class="mt-6 flex flex-col sm:flex-row flex-wrap items-stretch sm:items-center gap-3">
                            <a href="https://chatgpt.com/g/g-68b45e513c9881918831ae2a2dc294a5" target="_blank" rel="noopener noreferrer" class="inline-flex items-center justify-center gap-2 rounded-md border border-[#10a37f]/20 bg-[#f0fdf9] px-6 py-3 text-sm font-medium text-[#0c8c67] hover:bg-[#dcfce7] dark:border-[#10a37f]/30 dark:bg-[#052e21] dark:text-[#6ee7b7] dark:hover:bg-[#064e3b]">
                                Try the Custom GPT
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4"><path fill-rule="evenodd" d="M5 10a1 1 0 011-1h5.586L9.293 6.707a1 1 0 111.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 11-1.414-1.414L11.586 11H6a1 1 0 01-1-1z" clip-rule="evenodd" /></svg>
                            </a>
                        </div>
                    </div>
                    <div class="min-w-0">
                        <div class="rounded-md border border-black/10 bg-[#FCFCFB] p-4 dark:border-white/10 dark:bg-[#0f0f0f]">
                            <div class="mb-2 text-[12px] tracking-wide text-[#706f6c] uppercase dark:text-[#A1A09A]">How to Use</div>
                            <ol class="mb-3 list-decimal space-y-2 pl-5 text-[13px] text-[#575654] dark:text-[#A1A09A]">
                                <li>Click "Try the Custom GPT" to open our specialized ChatGPT</li>
                                <li>The GPT has built-in access to all MCP fantasy football tools</li>
                                <li>Ask questions like:
                                    <ul class="mt-2 list-disc space-y-1 pl-5">
                                        <li>"What's the current ADP for Christian McCaffrey?"</li>
                                        <li>"Who are the top trending players this week?"</li>
                                        <li>"Help me with my draft strategy for a 10-team league"</li>
                                        <li>"Analyze this trade: I give up CMC for Bijan Robinson"</li>
                                    </ul>
                                </li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6 sm:mt-8 rounded-xl border border-black/10 bg-[#FCFCFB] p-4 sm:p-5 shadow-sm dark:border-white/10 dark:bg-[#0f0f0f]">
                <div class="mb-3 text-[12px] font-semibold tracking-wide text-[#706f6c] uppercase dark:text-[#A1A09A]">Custom GPT Features</div>
                <div class="grid md:grid-cols-2 gap-6">
                    <div>
                        <h3 class="font-semibold text-[#1b1b18] dark:text-[#EDEDEC] mb-2">Real-Time Data Access</h3>
                        <ul class="list-disc space-y-1 pl-4 sm:pl-5 text-[13px] text-[#575654] dark:text-[#A1A09A]">
                            <li>Live player statistics and projections</li>
                            <li>Current ADP values from multiple sources</li>
                            <li>Trending adds/drops data</li>
                            <li>League standings and matchups</li>
                        </ul>
                    </div>
                    <div>
                        <h3 class="font-semibold text-[#1b1b18] dark:text-[#EDEDEC] mb-2">Intelligent Analysis</h3>
                        <ul class="list-disc space-y-1 pl-4 sm:pl-5 text-[13px] text-[#575654] dark:text-[#A1A09A]">
                            <li>Draft strategy recommendations</li>
                            <li>Trade analysis and suggestions</li>
                            <li>Waiver wire priorities</li>
                            <li>Lineup optimization advice</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="mt-6 rounded-xl border border-black/10 bg-[#FCFCFB] p-4 sm:p-5 shadow-sm dark:border-white/10 dark:bg-[#0f0f0f]">
                <div class="mb-3 text-[12px] font-semibold tracking-wide text-[#706f6c] uppercase dark:text-[#A1A09A]">Example Conversations</div>
                <div class="space-y-4 text-[13px] text-[#575654] dark:text-[#A1A09A]">
                    <div class="border-l-4 border-[#10a37f] pl-4">
                        <div class="font-medium text-[#1b1b18] dark:text-[#EDEDEC]">Draft Strategy</div>
                        <div class="italic">"I'm in a 12-team PPR league. What's my strategy for the first 8 picks?"</div>
                    </div>
                    <div class="border-l-4 border-[#10a37f] pl-4">
                        <div class="font-medium text-[#1b1b18] dark:text-[#EDEDEC]">Trade Analysis</div>
                        <div class="italic">"Should I trade Davante Adams for Amari Cooper and a 2025 1st round pick?"</div>
                    </div>
                    <div class="border-l-4 border-[#10a37f] pl-4">
                        <div class="font-medium text-[#1b1b18] dark:text-[#EDEDEC]">Player Research</div>
                        <div class="italic">"Compare Brock Bowers and Keon Coleman for my WR2 spot"</div>
                    </div>
                    <div class="border-l-4 border-[#10a37f] pl-4">
                        <div class="font-medium text-[#1b1b18] dark:text-[#EDEDEC]">Waiver Priorities</div>
                        <div class="italic">"Who should I target on waivers this week in PPR?"</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app.marketing>
