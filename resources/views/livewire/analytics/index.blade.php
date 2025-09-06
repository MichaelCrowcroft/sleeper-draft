<?php

use App\Models\ApiAnalytics;
use Livewire\Volt\Component;

new class extends Component {
    public function getAnalyticsProperty()
    {
        return ApiAnalytics::with('user')
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();
    }

    public function getStatsProperty()
    {
        return $this->getAnalyticsStats();
    }

    /**
     * Get analytics summary statistics
     */
    private function getAnalyticsStats(): array
    {
        $totalRequests = ApiAnalytics::count();
        $totalErrors = ApiAnalytics::where('has_error', true)->count();
        $errorRate = $totalRequests > 0 ? round(($totalErrors / $totalRequests) * 100, 2) : 0;

        $avgResponseTime = ApiAnalytics::whereNotNull('duration_ms')
            ->avg('duration_ms');

        $requestsLast24h = ApiAnalytics::where('created_at', '>=', now()->subDay())->count();

        // Get requests by endpoint category
        $requestsByCategory = ApiAnalytics::selectRaw('endpoint_category, COUNT(*) as count')
            ->groupBy('endpoint_category')
            ->get()
            ->pluck('count', 'endpoint_category')
            ->toArray();

        // Get most popular tools
        $popularTools = ApiAnalytics::selectRaw('tool_name, COUNT(*) as count')
            ->whereNotNull('tool_name')
            ->groupBy('tool_name')
            ->orderBy('count', 'desc')
            ->limit(5)
            ->get()
            ->pluck('count', 'tool_name')
            ->toArray();

        return [
            'total_requests' => $totalRequests,
            'total_errors' => $totalErrors,
            'error_rate' => $errorRate,
            'avg_response_time' => round($avgResponseTime ?? 0, 2),
            'requests_last_24h' => $requestsLast24h,
            'requests_by_category' => $requestsByCategory,
            'popular_tools' => $popularTools,
        ];
    }
}; ?>

<section class="w-full">
    <div class="py-12">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                API Analytics Dashboard
            </h1>
            <p class="text-gray-600 dark:text-gray-400 mt-2">
                Monitor and analyze API usage patterns, performance, and errors.
            </p>
        </div>
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <flux:callout class="p-6">
                    <flux:heading size="lg">Total Requests</flux:heading>
                    <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($this->stats['total_requests']) }}</div>
                </flux:callout>

                <flux:callout class="p-6">
                    <flux:heading size="lg">Error Rate</flux:heading>
                    <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $this->stats['error_rate'] }}%</div>
                </flux:callout>

                <flux:callout class="p-6">
                    <flux:heading size="lg">Avg Response Time</flux:heading>
                    <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $this->stats['avg_response_time'] }}ms</div>
                </flux:callout>

                <flux:callout class="p-6">
                    <flux:heading size="lg">Last 24h</flux:heading>
                    <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($this->stats['requests_last_24h']) }}</div>
                </flux:callout>
            </div>

            <!-- Category Breakdown -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <flux:callout class="p-6">
                    <flux:heading size="lg">Requests by Category</flux:heading>
                    <div class="space-y-2">
                        @foreach($this->stats['requests_by_category'] as $category => $count)
                            <div class="flex justify-between items-center">
                                <span class="text-sm font-medium text-green-600">
                                    {{ ucfirst($category ?? 'Other') }}
                                </span>
                                <span class="text-sm text-gray-500">{{ number_format($count) }}</span>
                            </div>
                        @endforeach
                        @if(empty($this->stats['requests_by_category']))
                            <p class="text-sm text-gray-500">No data available</p>
                        @endif
                    </div>
                </flux:callout>

                <flux:callout class="p-6">
                    <flux:heading size="lg">Popular Tools</flux:heading>
                    <div class="space-y-2">
                        @foreach($this->stats['popular_tools'] as $tool => $count)
                            <div class="flex justify-between items-center">
                                <span class="text-sm font-medium text-green-600">{{ $tool }}</span>
                                <span class="text-sm text-gray-500">{{ number_format($count) }}</span>
                            </div>
                        @endforeach
                        @if(empty($this->stats['popular_tools']))
                            <p class="text-sm text-gray-500">No data available</p>
                        @endif
                    </div>
                </flux:callout>
            </div>

            <!-- Recent Analytics Table -->
            <flux:callout class="p-6">
                <div class="flex justify-between items-center mb-6">
                    <flux:heading size="lg">Recent API Requests</flux:heading>
</div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Time
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Method
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Endpoint
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Tool
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Status
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Duration
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse($this->analytics as $analytic)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800" wire:key="analytic-{{ $analytic->id }}">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                        {{ $analytic->created_at->format('M j, H:i:s') }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                            {{ $analytic->method }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        <div class="max-w-xs truncate" title="{{ $analytic->endpoint }}">
                                            {{ $analytic->endpoint }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                        @if($analytic->tool_name)
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                                {{ $analytic->tool_name }}
                                            </span>
                                        @else
                                            <span class="text-gray-400">-</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                            {{ $analytic->status_code }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        @if($analytic->duration_ms)
                                            {{ $analytic->duration_ms }}ms
                                        @else
                                            <span class="text-gray-400">-</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <flux:button variant="ghost" size="sm" href="{{ route('analytics.show', $analytic->id) }}">
                                            View Details
                                        </flux:button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                        <div class="flex flex-col items-center">
                                            <p>No analytics data available yet.</p>
                                            <p class="text-xs mt-1">Make some API requests to see analytics here.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </flux:callout>
        </div>
    </div>
</section>