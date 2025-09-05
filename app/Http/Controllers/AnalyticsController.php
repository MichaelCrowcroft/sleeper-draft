<?php

namespace App\Http\Controllers;

use App\Models\ApiAnalytics;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    /**
     * Display the analytics dashboard
     */
    public function index(Request $request)
    {
        // Get the most recent 20 analytics entries
        $analytics = ApiAnalytics::with('user')
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        // Get some summary statistics
        $stats = $this->getAnalyticsStats();

        return view('analytics.index', compact('analytics', 'stats'));
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

        // Get status code distribution
        $statusCodes = ApiAnalytics::selectRaw('status_code, COUNT(*) as count')
            ->groupBy('status_code')
            ->get()
            ->pluck('count', 'status_code')
            ->toArray();

        return [
            'total_requests' => $totalRequests,
            'total_errors' => $totalErrors,
            'error_rate' => $errorRate,
            'avg_response_time' => round($avgResponseTime ?? 0, 2),
            'requests_last_24h' => $requestsLast24h,
            'requests_by_category' => $requestsByCategory,
            'popular_tools' => $popularTools,
            'status_codes' => $statusCodes,
        ];
    }

    /**
     * Get analytics data for a specific date range
     */
    public function filter(Request $request)
    {
        $startDate = $request->get('start_date') ? Carbon::parse($request->get('start_date')) : now()->subDays(7);
        $endDate = $request->get('end_date') ? Carbon::parse($request->get('end_date')) : now();
        $category = $request->get('category');
        $limit = $request->get('limit', 20);

        $query = ApiAnalytics::with('user')
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($category) {
            $query->where('endpoint_category', $category);
        }

        $analytics = $query->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        $stats = $this->getAnalyticsStats();

        return view('analytics.index', compact('analytics', 'stats', 'startDate', 'endDate', 'category'));
    }

    /**
     * Show detailed view of a specific analytics entry
     */
    public function show($id)
    {
        $analytic = ApiAnalytics::with('user')->findOrFail($id);

        return view('analytics.show', compact('analytic'));
    }
}
