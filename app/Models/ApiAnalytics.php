<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiAnalytics extends Model
{
    protected $table = 'api_analytics';

    protected $fillable = [
        'method',
        'endpoint',
        'route_name',
        'user_agent',
        'ip_address',
        'headers',
        'request_payload',
        'query_parameters',
        'status_code',
        'response_data',
        'response_size_bytes',
        'request_started_at',
        'request_completed_at',
        'duration_ms',
        'user_id',
        'api_key_hash',
        'is_authenticated',
        'has_error',
        'error_type',
        'error_message',
        'endpoint_category',
        'tool_name',
        'referrer',
        'memory_peak_usage_kb',
        'database_queries_count',
    ];

    protected $casts = [
        'headers' => 'array',
        'request_payload' => 'array',
        'query_parameters' => 'array',
        'response_data' => 'array',
        'request_started_at' => 'datetime',
        'request_completed_at' => 'datetime',
        'is_authenticated' => 'boolean',
        'has_error' => 'boolean',
        'memory_peak_usage_kb' => 'integer',
        'database_queries_count' => 'integer',
        'response_size_bytes' => 'integer',
        'duration_ms' => 'integer',
    ];

    /**
     * Relationship with User model
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for filtering by endpoint category
     */
    public function scopeCategory($query, string $category)
    {
        return $query->where('endpoint_category', $category);
    }

    /**
     * Scope for filtering by date range
     */
    public function scopeDateRange($query, Carbon $start, Carbon $end)
    {
        return $query->whereBetween('created_at', [$start, $end]);
    }

    /**
     * Scope for filtering by status code range
     */
    public function scopeStatusCode($query, int $min = 200, int $max = 599)
    {
        return $query->whereBetween('status_code', [$min, $max]);
    }

    /**
     * Scope for filtering errors only
     */
    public function scopeErrors($query)
    {
        return $query->where('has_error', true);
    }

    /**
     * Scope for filtering successful requests only
     */
    public function scopeSuccessful($query)
    {
        return $query->where('has_error', false)
            ->whereBetween('status_code', [200, 299]);
    }

    /**
     * Get formatted duration for display
     */
    public function getFormattedDurationAttribute(): string
    {
        if ($this->duration_ms < 1000) {
            return $this->duration_ms.'ms';
        }

        return round($this->duration_ms / 1000, 2).'s';
    }

    /**
     * Get formatted response size for display
     */
    public function getFormattedResponseSizeAttribute(): string
    {
        if (! $this->response_size_bytes) {
            return 'Unknown';
        }

        $size = $this->response_size_bytes;

        if ($size < 1024) {
            return $size.' B';
        } elseif ($size < 1048576) {
            return round($size / 1024, 2).' KB';
        } elseif ($size < 1073741824) {
            return round($size / 1048576, 2).' MB';
        } else {
            return round($size / 1073741824, 2).' GB';
        }
    }

    /**
     * Check if this is a slow request (over 1 second)
     */
    public function isSlowRequest(): bool
    {
        return $this->duration_ms > 1000;
    }

    /**
     * Check if this is a large response (over 1MB)
     */
    public function isLargeResponse(): bool
    {
        return $this->response_size_bytes && $this->response_size_bytes > 1048576;
    }

    /**
     * Get endpoint category badge color for UI
     */
    public function getCategoryBadgeColor(): string
    {
        return match ($this->endpoint_category) {
            'mcp' => 'bg-blue-100 text-blue-800', // Direct MCP server endpoint
            'mcp_tools_api' => 'bg-cyan-100 text-cyan-800', // REST API MCP tools endpoints
            'openapi' => 'bg-green-100 text-green-800',
            'health' => 'bg-gray-100 text-gray-800',
            default => 'bg-purple-100 text-purple-800',
        };
    }

    /**
     * Get tool badge color for UI
     */
    public function getToolBadgeColor(): string
    {
        if (! $this->tool_name) {
            return 'bg-gray-100 text-gray-800';
        }

        // Color code different tool types
        $toolName = strtolower($this->tool_name);

        // Handle both old format (without prefix) and new format (with prefix)
        if (str_starts_with($toolName, 'mcp_fantasy-football-mcp_')) {
            $toolName = str_replace('mcp_fantasy-football-mcp_', '', $toolName);
        }

        return match ($toolName) {
            'fetch-trending-players',
            'fetch-adp-players' => 'bg-orange-100 text-orange-800',
            'fetch-user-leagues',
            'get-league' => 'bg-blue-100 text-blue-800',
            'draft-picks' => 'bg-purple-100 text-purple-800',
            'fetch-rosters',
            'fetch-matchups' => 'bg-green-100 text-green-800',
            'fetch-trades' => 'bg-yellow-100 text-yellow-800',
            default => 'bg-indigo-100 text-indigo-800',
        };
    }

    /**
     * Get formatted user agent for display
     */
    public function getFormattedUserAgentAttribute(): string
    {
        if (! $this->user_agent) {
            return 'Unknown';
        }

        $userAgent = $this->user_agent;

        // Map common user agents to cleaner display names
        $userAgentMap = [
            'ChatGPT-User' => 'ChatGPT',
            'Claude' => 'Claude',
            'Grok' => 'Grok',
            'curl' => 'cURL',
            'PostmanRuntime' => 'Postman',
            'Thunder Client' => 'Thunder Client',
            'Insomnia' => 'Insomnia',
            'Mozilla/5.0' => 'Browser',
            'python-requests' => 'Python Requests',
            'axios' => 'Axios',
            'fetch' => 'Fetch API',
            'Go-http-client' => 'Go HTTP Client',
            'Node.js' => 'Node.js',
            'PHP' => 'PHP',
            'Java' => 'Java',
            'C#' => 'C#',
            '.NET' => '.NET',
        ];

        foreach ($userAgentMap as $pattern => $displayName) {
            if (str_contains($userAgent, $pattern)) {
                return $displayName;
            }
        }

        // If no match found, return a truncated version
        return strlen($userAgent) > 30 ? substr($userAgent, 0, 27).'...' : $userAgent;
    }

    /**
     * Get status code badge color for UI
     */
    public function getStatusBadgeColor(): string
    {
        $status = $this->status_code;

        if ($status >= 200 && $status < 300) {
            return 'bg-green-100 text-green-800';
        } elseif ($status >= 300 && $status < 400) {
            return 'bg-blue-100 text-blue-800';
        } elseif ($status >= 400 && $status < 500) {
            return 'bg-yellow-100 text-yellow-800';
        } elseif ($status >= 500) {
            return 'bg-red-100 text-red-800';
        }

        return 'bg-gray-100 text-gray-800';
    }
}
