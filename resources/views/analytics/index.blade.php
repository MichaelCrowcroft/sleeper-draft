<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('API Analytics Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <flux:card>
                    <flux:heading size="lg">Total Requests</flux:heading>
                    <flux:subheading>{{ number_format($stats['total_requests']) }}</flux:subheading>
                </flux:card>

                <flux:card>
                    <flux:heading size="lg">Error Rate</flux:heading>
                    <flux:subheading>{{ $stats['error_rate'] }}%</flux:subheading>
                </flux:card>

                <flux:card>
                    <flux:heading size="lg">Avg Response Time</flux:heading>
                    <flux:subheading>{{ $stats['avg_response_time'] }}ms</flux:subheading>
                </flux:card>

                <flux:card>
                    <flux:heading size="lg">Last 24h</flux:heading>
                    <flux:subheading>{{ number_format($stats['requests_last_24h']) }}</flux:subheading>
                </flux:card>
            </div>

            <!-- Category Breakdown -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <flux:card>
                    <flux:heading size="lg">Requests by Category</flux:heading>
                    <div class="space-y-2">
                        @foreach($stats['requests_by_category'] as $category => $count)
                            <div class="flex justify-between items-center">
                                <span class="text-sm font-medium {{ $category === 'mcp' ? 'text-blue-600' : ($category === 'openapi' ? 'text-green-600' : 'text-gray-600') }}">
                                    {{ ucfirst($category ?? 'Other') }}
                                </span>
                                <span class="text-sm text-gray-500">{{ number_format($count) }}</span>
                            </div>
                        @endforeach
                        @if(empty($stats['requests_by_category']))
                            <p class="text-sm text-gray-500">No data available</p>
                        @endif
                    </div>
                </flux:card>

                <flux:card>
                    <flux:heading size="lg">Popular Tools</flux:heading>
                    <div class="space-y-2">
                        @foreach($stats['popular_tools'] as $tool => $count)
                            <div class="flex justify-between items-center">
                                <span class="text-sm font-medium text-purple-600">{{ $tool }}</span>
                                <span class="text-sm text-gray-500">{{ number_format($count) }}</span>
                            </div>
                        @endforeach
                        @if(empty($stats['popular_tools']))
                            <p class="text-sm text-gray-500">No data available</p>
                        @endif
                    </div>
                </flux:card>
            </div>

            <!-- Recent Analytics Table -->
            <flux:card>
                <div class="flex justify-between items-center mb-6">
                    <flux:heading size="lg">Recent API Requests</flux:heading>
                    <div class="flex space-x-2">
                        <flux:button variant="ghost" href="{{ route('analytics.filter', ['start_date' => now()->subDay()->format('Y-m-d'), 'end_date' => now()->format('Y-m-d')]) }}">
                            Last 24h
                        </flux:button>
                        <flux:button variant="ghost" href="{{ route('analytics.filter', ['start_date' => now()->subWeek()->format('Y-m-d'), 'end_date' => now()->format('Y-m-d')]) }}">
                            Last 7 days
                        </flux:button>
                    </div>
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
                                    Category
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Status
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Duration
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    IP
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse($analytics as $analytic)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                        {{ $analytic->created_at->format('M j, H:i:s') }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full
                                            {{ $analytic->method === 'GET' ? 'bg-green-100 text-green-800' :
                                               ($analytic->method === 'POST' ? 'bg-blue-100 text-blue-800' :
                                               ($analytic->method === 'PUT' ? 'bg-yellow-100 text-yellow-800' :
                                               ($analytic->method === 'DELETE' ? 'bg-red-100 text-red-800' :
                                               'bg-gray-100 text-gray-800'))) }}">
                                            {{ $analytic->method }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        <div class="max-w-xs truncate" title="{{ $analytic->endpoint }}">
                                            {{ $analytic->endpoint }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                        @if($analytic->endpoint_category)
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $analytic->getCategoryBadgeColor() }}">
                                                {{ ucfirst($analytic->endpoint_category) }}
                                            </span>
                                        @else
                                            <span class="text-gray-400">-</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $analytic->getStatusBadgeColor() }}">
                                            {{ $analytic->status_code }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        @if($analytic->duration_ms)
                                            {{ $analytic->formatted_duration }}
                                        @else
                                            <span class="text-gray-400">-</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ $analytic->ip_address }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <flux:button variant="ghost" size="sm" href="{{ route('analytics.show', $analytic->id) }}">
                                            View Details
                                        </flux:button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                        <div class="flex flex-col items-center">
                                            <svg class="w-8 h-8 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                            </svg>
                                            <p>No analytics data available yet.</p>
                                            <p class="text-xs mt-1">Make some API requests to see analytics here.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($analytics->count() >= 20)
                    <div class="mt-6 text-center">
                        <flux:button variant="outline" href="{{ route('analytics.filter', ['limit' => 50]) }}">
                            Load More (50)
                        </flux:button>
                    </div>
                @endif
            </flux:card>
        </div>
    </div>
</x-app-layout>
