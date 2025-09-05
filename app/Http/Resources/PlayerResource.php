<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlayerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            // Core player identification
            'player_id' => $this->player_id,
            'full_name' => $this->full_name,

            // Position and team information
            'position' => $this->position,
            'team' => $this->team,
            'active' => $this->active,

            // Physical and career attributes
            'age' => $this->age,
            'years_exp' => $this->years_exp,
            'number' => $this->number,
            'height' => $this->height,
            'weight' => $this->weight,

            // Health and status
            'injury_status' => $this->injury_status,
            'injury_start_date' => $this->injury_start_date,
            'news_updated' => $this->news_updated,

            // ADP (Average Draft Position) data
            'adp' => $this->adp,
            'adp_formatted' => $this->adp_formatted,

            // Trending data
            'adds_24h' => $this->adds_24h,
            'drops_24h' => $this->drops_24h,
            'times_drafted' => $this->times_drafted,

            // League scheduling
            'bye_week' => $this->bye_week,
        ];

        // Append 2024 season PPR summary if available
        if (method_exists($this->resource, 'getSeason2024Summary')) {
            $data['season_2024_summary'] = $this->resource->getSeason2024Summary();
        }

        // Append 2025 projections PPR summary if available
        if (method_exists($this->resource, 'getSeason2025ProjectionSummary')) {
            $data['season_2025_projection_summary'] = $this->resource->getSeason2025ProjectionSummary();
        }

        // Append upcoming week's projection data if available
        if ($this->resource->relationLoaded('projections')) {
            $upcomingProjections = $this->resource->getRelation('projections');
            if ($upcomingProjections->isNotEmpty()) {
                $upcomingProjection = $upcomingProjections->first();
                $data['upcoming_week_projection'] = [
                    'season' => $upcomingProjection->season,
                    'week' => $upcomingProjection->week,
                    'game_date' => $upcomingProjection->game_date,
                    'opponent' => $upcomingProjection->opponent,
                    'pts_ppr' => $upcomingProjection->pts_ppr ?? ($upcomingProjection->stats['pts_ppr'] ?? null),
                    'gms_active' => $upcomingProjection->gms_active ?? ($upcomingProjection->stats['gms_active'] ?? null),
                    'stats' => $upcomingProjection->stats,
                    'source' => $upcomingProjection->source,
                    'season_type' => $upcomingProjection->season_type,
                ];
            }
        }

        return $data;
    }
}
