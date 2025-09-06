@props([
    'title' => 'Performance Distribution',
    'subtitle' => 'PPR Fantasy Points',
    'data' => null,
    'width' => 480.0,
    'height' => 120.0,
])

@if($data && $data['exists'] ?? false)
    <div class="w-full">
        <div class="text-center mb-3">
            <div class="text-sm font-medium text-gray-700">{{ $title }}</div>
            <div class="text-xs text-gray-500 mt-1">{{ $subtitle }}</div>
        </div>
        <svg viewBox="0 0 {{ $data['width'] }} {{ $data['height'] }}" class="w-full h-[140px] drop-shadow-sm">
            <!-- subtle background -->
            <rect x="0" y="0" width="{{ $data['width'] }}" height="{{ $data['height'] }}" fill="#fefefe" rx="8" />

            <!-- light grid line at center for reference -->
            <line x1="{{ $data['width'] * 0.1 }}" x2="{{ $data['width'] * 0.9 }}" y1="{{ $data['yMid'] }}" y2="{{ $data['yMid'] }}" stroke="#f3f4f6" stroke-width="1" stroke-dasharray="2,3" />

            <!-- whiskers with gradient effect -->
            <line x1="{{ $data['xMin'] }}" x2="{{ $data['xQ1'] }}" y1="{{ $data['yMid'] }}" y2="{{ $data['yMid'] }}" stroke="#059669" stroke-width="3" stroke-linecap="round">
                <title>Min: {{ number_format($data['vMin'], 1) }}</title>
            </line>
            <line x1="{{ $data['xQ3'] }}" x2="{{ $data['xMax'] }}" y1="{{ $data['yMid'] }}" y2="{{ $data['yMid'] }}" stroke="#059669" stroke-width="3" stroke-linecap="round">
                <title>Max: {{ number_format($data['vMax'], 1) }}</title>
            </line>

            <!-- min/max caps with rounded ends -->
            <line x1="{{ $data['xMin'] }}" x2="{{ $data['xMin'] }}" y1="{{ $data['yMid'] - 8 }}" y2="{{ $data['yMid'] + 8 }}" stroke="#059669" stroke-width="3" stroke-linecap="round" />
            <line x1="{{ $data['xMax'] }}" x2="{{ $data['xMax'] }}" y1="{{ $data['yMid'] - 8 }}" y2="{{ $data['yMid'] + 8 }}" stroke="#059669" stroke-width="3" stroke-linecap="round" />

            <!-- box (Q1–Q3) with gradient and shadow effect -->
            <defs>
                <linearGradient id="boxGradient" x1="0%" y1="0%" x2="0%" y2="100%">
                    <stop offset="0%" style="stop-color:#d1fae5;stop-opacity:0.9" />
                    <stop offset="100%" style="stop-color:#a7f3d0;stop-opacity:0.7" />
                </linearGradient>
                <filter id="boxShadow" x="-20%" y="-20%" width="140%" height="140%">
                    <feDropShadow dx="0" dy="2" stdDeviation="2" flood-opacity="0.15"/>
                </filter>
            </defs>
            <rect x="{{ $data['xQ1'] }}" y="{{ $data['yMid'] - ($data['boxH']/2) }}" width="{{ max(1, $data['xQ3'] - $data['xQ1']) }}" height="{{ $data['boxH'] }}" fill="url(#boxGradient)" stroke="#059669" stroke-width="2.5" rx="6" filter="url(#boxShadow)">
                <title>Q1–Q3: {{ number_format($data['vQ1'], 1) }} – {{ number_format($data['vQ3'], 1) }}</title>
            </rect>

            <!-- median line with emphasis -->
            <line x1="{{ $data['xMedian'] }}" x2="{{ $data['xMedian'] }}" y1="{{ $data['yMid'] - ($data['boxH']/2) }}" y2="{{ $data['yMid'] + ($data['boxH']/2) }}" stroke="#047857" stroke-width="3" stroke-linecap="round">
                <title>Median: {{ number_format($data['vMedian'], 1) }}</title>
            </line>

            <!-- elegant numeric labels with better typography -->
            <!-- min -->
            <text x="{{ $data['xMin'] }}" y="{{ $data['yMid'] - ($data['boxH']/2) - 8 }}" text-anchor="middle" font-size="11" font-weight="600" fill="#1f2937">{{ number_format($data['vMin'], 1) }}</text>
            <text x="{{ $data['xMin'] }}" y="{{ $data['yMid'] + ($data['boxH']/2) + 16 }}" text-anchor="middle" font-size="9" fill="#6b7280">MIN</text>

            <!-- Q1 -->
            <text x="{{ $data['xQ1'] }}" y="{{ $data['yMid'] + ($data['boxH']/2) + 16 }}" text-anchor="middle" font-size="10" font-weight="500" fill="#059669">{{ number_format($data['vQ1'], 1) }}</text>
            <text x="{{ $data['xQ1'] }}" y="{{ $data['yMid'] + ($data['boxH']/2) + 26 }}" text-anchor="middle" font-size="8" fill="#6b7280">Q1</text>

            <!-- median (emphasized) -->
            <text x="{{ $data['xMedian'] }}" y="{{ $data['yMid'] - ($data['boxH']/2) - 8 }}" text-anchor="middle" font-size="12" font-weight="700" fill="#047857">{{ number_format($data['vMedian'], 1) }}</text>
            <text x="{{ $data['xMedian'] }}" y="{{ $data['yMid'] - ($data['boxH']/2) - 18 }}" text-anchor="middle" font-size="9" fill="#047857">MEDIAN</text>

            <!-- Q3 -->
            <text x="{{ $data['xQ3'] }}" y="{{ $data['yMid'] + ($data['boxH']/2) + 16 }}" text-anchor="middle" font-size="10" font-weight="500" fill="#059669">{{ number_format($data['vQ3'], 1) }}</text>
            <text x="{{ $data['xQ3'] }}" y="{{ $data['yMid'] + ($data['boxH']/2) + 26 }}" text-anchor="middle" font-size="8" fill="#6b7280">Q3</text>

            <!-- max -->
            <text x="{{ $data['xMax'] }}" y="{{ $data['yMid'] - ($data['boxH']/2) - 8 }}" text-anchor="middle" font-size="11" font-weight="600" fill="#1f2937">{{ number_format($data['vMax'], 1) }}</text>
            <text x="{{ $data['xMax'] }}" y="{{ $data['yMid'] + ($data['boxH']/2) + 16 }}" text-anchor="middle" font-size="9" fill="#6b7280">MAX</text>
        </svg>
    </div>
@else
    <div class="w-full">
        <div class="text-center mb-3">
            <div class="text-sm font-medium text-gray-700">{{ $title }}</div>
            <div class="text-xs text-gray-500 mt-1">{{ $subtitle }}</div>
        </div>
        <div class="text-center py-8 text-muted-foreground text-sm">
            No data available for visualization.
        </div>
    </div>
@endif
