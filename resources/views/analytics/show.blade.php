<x-layouts.app title="Analytics Details">
    <div class="py-12">
        <div class="mb-6">
            <div class="flex items-center space-x-4 mb-4">
                <flux:button variant="ghost" href="{{ route('analytics.index') }}">
                    ← Back to Analytics
                </flux:button>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                Analytics Details
            </h1>
            <p class="text-gray-600 dark:text-gray-400 mt-2">
                Detailed information about this API request.
            </p>
        </div>
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Request Information -->
                <flux:callout class="p-6">
                    <flux:heading size="lg">Request Information</flux:heading>

                    <div class="space-y-4">
                        <div>
                            <flux:label>Timestamp</flux:label>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                {{ $analytic->created_at->format('F j, Y \a\t g:i:s A') }}
                            </p>
                        </div>

                        <div>
                            <flux:label>Method & Endpoint</flux:label>
                            <div class="flex items-center space-x-2">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full
                                    {{ $analytic->method === 'GET' ? 'bg-green-100 text-green-800' :
                                       ($analytic->method === 'POST' ? 'bg-blue-100 text-blue-800' :
                                       ($analytic->method === 'PUT' ? 'bg-yellow-100 text-yellow-800' :
                                       ($analytic->method === 'DELETE' ? 'bg-red-100 text-red-800' :
                                       'bg-gray-100 text-gray-800'))) }}">
                                    {{ $analytic->method }}
                                </span>
                                <code class="text-sm bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded">
                                    {{ $analytic->endpoint }}
                                </code>
                            </div>
                        </div>

                        <div>
                            <flux:label>Route Name</flux:label>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                {{ $analytic->route_name ?? 'N/A' }}
                            </p>
                        </div>

                        <div>
                            <flux:label>IP Address</flux:label>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                {{ $analytic->ip_address }}
                            </p>
                        </div>

                        <div>
                            <flux:label>User Agent</flux:label>
                            <p class="text-sm text-gray-600 dark:text-gray-400 break-all">
                                {{ $analytic->user_agent ?? 'N/A' }}
                            </p>
                        </div>

                        @if($analytic->user)
                            <div>
                                <flux:label>Authenticated User</flux:label>
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    {{ $analytic->user->name }} (ID: {{ $analytic->user->id }})
                                </p>
                            </div>
                        @endif
                    </div>
                </flux:callout>

                <!-- Response Information -->
                <flux:callout class="p-6">
                    <flux:heading size="lg">Response Information</flux:heading>

                    <div class="space-y-4">
                        <div>
                            <flux:label>Status Code</flux:label>
                            <span class="px-2 py-1 text-sm font-semibold rounded-full {{ $analytic->getStatusBadgeColor() }}">
                                {{ $analytic->status_code }}
                            </span>
                        </div>

                        <div>
                            <flux:label>Response Time</flux:label>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                @if($analytic->duration_ms)
                                    {{ $analytic->formatted_duration }}
                                @else
                                    N/A
                                @endif
                            </p>
                        </div>

                        <div>
                            <flux:label>Response Size</flux:label>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                @if($analytic->response_size_bytes)
                                    {{ $analytic->formatted_response_size }}
                                @else
                                    N/A
                                @endif
                            </p>
                        </div>

                        <div>
                            <flux:label>Category</flux:label>
                            @if($analytic->endpoint_category)
                                <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $analytic->getCategoryBadgeColor() }}">
                                    {{ ucfirst($analytic->endpoint_category) }}
                                </span>
                            @else
                                <span class="text-gray-400">N/A</span>
                            @endif
                        </div>

                        @if($analytic->tool_name)
                            <div>
                                <flux:label>Tool Name</flux:label>
                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-purple-100 text-purple-800">
                                    {{ $analytic->tool_name }}
                                </span>
                            </div>
                        @endif

                        @if($analytic->has_error)
                            <div>
                                <flux:label>Error Information</flux:label>
                                <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded p-3">
                                    <p class="text-sm font-medium text-red-800 dark:text-red-200">
                                        {{ $analytic->error_type ?? 'Error' }}
                                    </p>
                                    @if($analytic->error_message)
                                        <p class="text-sm text-red-600 dark:text-red-300 mt-1">
                                            {{ $analytic->error_message }}
                                        </p>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                </flux:callout>

                <!-- Request Payload -->
                @if($analytic->request_payload)
                    <flux:callout class="p-6">
                        <flux:heading size="lg">Request Payload</flux:heading>
                        <div class="bg-gray-100 dark:bg-gray-800 rounded p-4 overflow-x-auto">
                            <pre class="text-xs text-gray-900 dark:text-gray-200 whitespace-pre-wrap">
{{ json_encode($analytic->request_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                        </div>
                    </flux:callout>
                @endif

                <!-- Response Data -->
                @if($analytic->response_data && is_array($analytic->response_data))
                    <flux:callout class="p-6">
                        <flux:heading size="lg">Response Data</flux:heading>
                        <div class="bg-gray-100 dark:bg-gray-800 rounded p-4 overflow-x-auto">
                            <pre class="text-xs text-gray-900 dark:text-gray-200 whitespace-pre-wrap">
{{ json_encode($analytic->response_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                        </div>
                    </flux:callout>
                @endif

                <!-- Query Parameters -->
                @if($analytic->query_parameters)
                    <flux:callout class="p-6">
                        <flux:heading size="lg">Query Parameters</flux:heading>
                        <div class="bg-gray-100 dark:bg-gray-800 rounded p-4 overflow-x-auto">
                            <pre class="text-xs text-gray-900 dark:text-gray-200 whitespace-pre-wrap">
{{ json_encode($analytic->query_parameters, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                        </div>
                    </flux:callout>
                @endif

                <!-- Headers -->
                @if($analytic->headers)
                    <flux:callout class="p-6">
                        <flux:heading size="lg">Request Headers</flux:heading>
                        <div class="bg-gray-100 dark:bg-gray-800 rounded p-4 overflow-x-auto">
                            <pre class="text-xs text-gray-900 dark:text-gray-200 whitespace-pre-wrap">
{{ json_encode($analytic->headers, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                        </div>
                    </flux:callout>
                @endif

                <!-- Performance Metrics -->
                <flux:callout class="p-6">
                    <flux:heading size="lg">Performance Metrics</flux:heading>

                    <div class="space-y-4">
                        <div>
                            <flux:label>Memory Usage</flux:label>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                @if($analytic->memory_peak_usage_kb)
                                    {{ number_format($analytic->memory_peak_usage_kb) }} KB
                                @else
                                    N/A
                                @endif
                            </p>
                        </div>

                        <div>
                            <flux:label>Database Queries</flux:label>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                @if($analytic->database_queries_count !== null)
                                    {{ number_format($analytic->database_queries_count) }}
                                @else
                                    N/A
                                @endif
                            </p>
                        </div>

                        @if($analytic->referrer)
                            <div>
                                <flux:label>Referrer</flux:label>
                                <p class="text-sm text-gray-600 dark:text-gray-400 break-all">
                                    {{ $analytic->referrer }}
                                </p>
                            </div>
                        @endif

                        <div>
                            <flux:label>Processing Timeline</flux:label>
                            <div class="text-sm text-gray-600 dark:text-gray-400 space-y-1">
                                <p>Started: {{ $analytic->request_started_at?->format('H:i:s.u') }}</p>
                                <p>Completed: {{ $analytic->request_completed_at?->format('H:i:s.u') }}</p>
                            </div>
                        </div>
                    </div>
                </flux:callout>
            </div>
        </div>
    </div>
</x-layouts.app>
