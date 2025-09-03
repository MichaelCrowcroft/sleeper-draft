<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Models\ApiAnalytics;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ApiAnalyticsMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        // Start timing
        $startTime = now();
        $startMemory = memory_get_peak_usage(true);

        // Count database queries before processing
        $initialQueryCount = DB::getQueryLog() ? count(DB::getQueryLog()) : 0;

        // Process the request
        $response = $next($request);

        // End timing
        $endTime = now();
        $endMemory = memory_get_peak_usage(true);

        // Count database queries after processing
        $finalQueryCount = DB::getQueryLog() ? count(DB::getQueryLog()) : 0;
        $queryCount = $finalQueryCount - $initialQueryCount;

        // Calculate duration
        $duration = $startTime->diffInMilliseconds($endTime);

        // Get response content (safely handle different response types)
        $responseContent = $this->getResponseContent($response);
        $responseSize = strlen($responseContent);

        // Determine if this is an error response
        $isError = $response->getStatusCode() >= 400;
        $errorType = $this->determineErrorType($response, $isError);

        // Extract tool name for MCP endpoints
        $toolName = $this->extractToolName($request);

        // Determine endpoint category
        $endpointCategory = $this->determineEndpointCategory($request);

        // Collect analytics data
        $analyticsData = [
            'method' => $request->method(),
            'endpoint' => $request->path(),
            'route_name' => $request->route() ? $request->route()->getName() : null,
            'user_agent' => $request->userAgent(),
            'ip_address' => $request->ip(),
            'headers' => $this->filterHeaders($request->headers->all()),
            'request_payload' => $this->getRequestPayload($request),
            'query_parameters' => $request->query(),
            'status_code' => $response->getStatusCode(),
            'response_data' => $this->getResponseData($response, $responseContent),
            'response_size_bytes' => $responseSize,
            'request_started_at' => $startTime,
            'request_completed_at' => $endTime,
            'duration_ms' => $duration,
            'user_id' => Auth::id(),
            'is_authenticated' => Auth::check(),
            'has_error' => $isError,
            'error_type' => $errorType,
            'error_message' => $isError ? $this->getErrorMessage($response, $responseContent) : null,
            'endpoint_category' => $endpointCategory,
            'tool_name' => $toolName,
            'referrer' => $request->header('referer'),
            'memory_peak_usage_kb' => round(($endMemory - $startMemory) / 1024),
            'database_queries_count' => $queryCount,
        ];

        // Store analytics data asynchronously to avoid blocking response
        $this->storeAnalyticsAsync($analyticsData);

        return $response;
    }

    /**
     * Get response content safely
     */
    private function getResponseContent($response): string
    {
        if ($response instanceof Response) {
            return $response->getContent();
        }

        if (method_exists($response, 'getContent')) {
            try {
                return $response->getContent();
            } catch (\Exception $e) {
                return '';
            }
        }

        return '';
    }

    /**
     * Get response data for analytics (truncated if too large)
     */
    private function getResponseData($response, string $content): ?array
    {
        // Skip if content is too large (> 1MB)
        if (strlen($content) > 1048576) {
            return ['truncated' => true, 'size' => strlen($content)];
        }

        // Try to decode JSON response
        if ($response->headers->get('Content-Type') === 'application/json') {
            try {
                $data = json_decode($content, true);
                return is_array($data) ? $data : ['content' => $content];
            } catch (\Exception $e) {
                return ['content' => $content];
            }
        }

        // For non-JSON responses, store a summary
        return ['content_type' => $response->headers->get('Content-Type'), 'size' => strlen($content)];
    }

    /**
     * Get request payload safely
     */
    private function getRequestPayload(Request $request): ?array
    {
        // Only capture payload for POST/PUT/PATCH requests
        if (!in_array($request->method(), ['POST', 'PUT', 'PATCH'])) {
            return null;
        }

        try {
            $payload = $request->all();

            // Remove sensitive data
            $sensitiveFields = ['password', 'password_confirmation', 'api_key', 'token', 'secret'];
            foreach ($sensitiveFields as $field) {
                if (isset($payload[$field])) {
                    $payload[$field] = '[FILTERED]';
                }
            }

            return $payload;
        } catch (\Exception $e) {
            return ['error' => 'Could not capture payload'];
        }
    }

    /**
     * Filter headers to remove sensitive information
     */
    private function filterHeaders(array $headers): array
    {
        $filtered = [];
        $allowedHeaders = [
            'accept',
            'accept-encoding',
            'accept-language',
            'cache-control',
            'content-type',
            'user-agent',
            'x-requested-with',
        ];

        foreach ($headers as $key => $value) {
            if (in_array(strtolower($key), $allowedHeaders)) {
                $filtered[$key] = is_array($value) ? implode(', ', $value) : $value;
            }
        }

        return $filtered;
    }

    /**
     * Determine error type from response
     */
    private function determineErrorType($response, bool $isError): ?string
    {
        if (!$isError) {
            return null;
        }

        $statusCode = $response->getStatusCode();

        if ($statusCode >= 400 && $statusCode < 500) {
            return 'client_error';
        } elseif ($statusCode >= 500) {
            return 'server_error';
        }

        return 'unknown_error';
    }

    /**
     * Get error message from response
     */
    private function getErrorMessage($response, string $content): ?string
    {
        try {
            if ($response->headers->get('Content-Type') === 'application/json') {
                $data = json_decode($content, true);
                if (is_array($data) && isset($data['message'])) {
                    return Str::limit($data['message'], 255);
                }
                if (is_array($data) && isset($data['error'])) {
                    return Str::limit($data['error'], 255);
                }
            }

            return Str::limit($content, 255);
        } catch (\Exception $e) {
            return 'Unable to parse error message';
        }
    }

    /**
     * Extract tool name from MCP routes
     */
    private function extractToolName(Request $request): ?string
    {
        $routeName = $request->route() ? $request->route()->getName() : null;
        $path = $request->path();

        // Handle API MCP routes
        if (!$routeName) {
            return null;
        }

        // Extract tool name from route names like 'api.mcp.fetch-trending-players'
        if (str_starts_with($routeName, 'api.mcp.')) {
            $toolName = str_replace('api.mcp.', '', $routeName);
            return 'mcp_fantasy-football-mcp_' . $toolName;
        }

        // Check if it's a generic MCP route with tool parameter
        if ($routeName === 'api.mcp.invoke' && $request->route('tool')) {
            return 'mcp_fantasy-football-mcp_' . $request->route('tool');
        }

        // Handle direct MCP endpoint with JSON-RPC payload
        if ($path === 'mcp') {
            try {
                $payload = $request->json();
                if ($payload && isset($payload['method']) && $payload['method'] === 'tools/call') {
                    if (isset($payload['params']['name'])) {
                        return 'mcp_fantasy-football-mcp_' . $payload['params']['name'];
                    }
                }
            } catch (\Exception $e) {
                // If JSON parsing fails, return null
                return null;
            }
        }

        return null;
    }

    /**
     * Determine endpoint category
     */
    private function determineEndpointCategory(Request $request): string
    {
        $path = $request->path();
        $routeName = $request->route() ? $request->route()->getName() : null;

        // Direct MCP endpoint (/mcp) - this is the proper MCP server endpoint
        if ($path === 'mcp') {
            return 'mcp';
        }

        // API MCP tools endpoints (/api/mcp/tools/*) - these are REST API endpoints
        if (str_starts_with($path, 'api/mcp')) {
            // Check if it's the generic invoke route with tool parameter
            if ($routeName === 'api.mcp.invoke' && $request->route('tool')) {
                return 'mcp_tools_api';
            }

            // Check if it's a specific tool route
            if (str_starts_with($routeName, 'api.mcp.')) {
                return 'mcp_tools_api';
            }
        }

        if (str_contains($path, 'openapi')) {
            return 'openapi';
        }

        if (str_contains($path, 'health')) {
            return 'health';
        }

        return 'api';
    }

    /**
     * Store analytics data asynchronously
     */
    private function storeAnalyticsAsync(array $data): void
    {
        try {
            // Store synchronously for now, but could be made async with queues
            ApiAnalytics::create($data);
        } catch (\Exception $e) {
            // Log analytics storage failure but don't break the response
            \Illuminate\Support\Facades\Log::error('Failed to store API analytics', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
        }
    }
}
