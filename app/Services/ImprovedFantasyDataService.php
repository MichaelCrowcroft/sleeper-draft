<?php

namespace App\Services;

use App\Services\SleeperSdk;
use App\Services\EspnSdk;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ImprovedFantasyDataService
{
    public function __construct(
        private readonly SleeperSdk $sleeperSdk,
        private readonly EspnSdk $espnSdk,
    ) {}

    /**
     * Get ADP data with fallbacks and validation
     */
    public function getValidatedAdp(string $season, string $format = 'redraft', string $sport = 'nfl', ?int $ttlSeconds = null): array
    {
        $ttlSeconds = $ttlSeconds ?? (int) config('services.sleeper.ttl.adp', 86400);
        $cacheKey = "improved_adp:$sport:$season:$format";

        return Cache::remember($cacheKey, now()->addSeconds($ttlSeconds), function () use ($season, $format, $sport) {
            // Try primary source (Sleeper)
            $adp = $this->sleeperSdk->getAdp($season, $format, $sport, ttlSeconds: 0);

            // Validate the data quality
            $isValid = $this->validateAdpData($adp);

            if ($isValid) {
                Log::info("Using valid Sleeper ADP data", ['count' => count($adp)]);
                return $adp;
            }

            Log::warning("Sleeper ADP data is invalid, attempting fallback", [
                'count' => count($adp),
                'sample' => array_slice($adp, 0, 3)
            ]);

            // Try fallback sources
            $fallbackAdp = $this->getFallbackAdpData($season, $format, $sport);

            if (!empty($fallbackAdp)) {
                Log::info("Using fallback ADP data", ['count' => count($fallbackAdp)]);
                return $fallbackAdp;
            }

            // Last resort: generate reasonable ADP based on player catalog
            Log::warning("All ADP sources failed, generating synthetic ADP");
            return $this->generateSyntheticAdp($season, $sport);
        });
    }

    /**
     * Get projections with fallbacks
     */
    public function getValidatedProjections(string $season, int $week, string $sport = 'nfl', ?int $ttlSeconds = null): array
    {
        $ttlSeconds = $ttlSeconds ?? (int) config('services.sleeper.ttl.projections', 600);
        $cacheKey = "improved_projections:$sport:$season:$week";

        return Cache::remember($cacheKey, now()->addSeconds($ttlSeconds), function () use ($season, $week, $sport) {
            // Try primary source (Sleeper)
            $projections = $this->sleeperSdk->getWeeklyProjections($season, $week, $sport, ttlSeconds: 0);

            // Validate the data
            $isValid = $this->validateProjectionsData($projections);

            if ($isValid) {
                Log::info("Using valid Sleeper projections data", ['count' => count($projections)]);
                return $projections;
            }

            Log::warning("Sleeper projections data is invalid, attempting fallback", [
                'count' => count($projections)
            ]);

            // Try fallback sources
            $fallbackProjections = $this->getFallbackProjections($season, $week, $sport);

            if (!empty($fallbackProjections)) {
                Log::info("Using fallback projections data", ['count' => count($fallbackProjections)]);
                return $fallbackProjections;
            }

            // Return empty array - draft board should handle this gracefully
            Log::warning("All projections sources failed, returning empty data");
            return [];
        });
    }

    /**
     * Validate ADP data quality
     */
    private function validateAdpData(array $adp): bool
    {
        if (empty($adp)) {
            return false;
        }

        // Check if we have reasonable data size
        if (count($adp) < 100) {
            return false;
        }

        // Check for known top players and their rankings
        $topPlayerIds = ['4046', '4034', '4881', '4037', '4039']; // CMC, Josh Allen, Tyreek, Ekeler, Kupp

        $foundValidPlayers = 0;
        foreach ($topPlayerIds as $playerId) {
            if (isset($adp[$playerId])) {
                $playerAdp = $adp[$playerId]['adp'] ?? 9999;
                // Top players should have ADP under 50, not 4000+
                if ($playerAdp < 50) {
                    $foundValidPlayers++;
                }
            }
        }

        // If we found at least 3 top players with reasonable rankings, data is valid
        return $foundValidPlayers >= 3;
    }

    /**
     * Validate projections data quality
     */
    private function validateProjectionsData(array $projections): bool
    {
        if (empty($projections)) {
            return false;
        }

        // Check if at least some players have projection data
        $playersWithPoints = 0;
        $sampleSize = min(50, count($projections));

        for ($i = 0; $i < $sampleSize; $i++) {
            $playerData = array_values($projections)[$i];
            $points = (float) (($playerData['pts_half_ppr'] ?? $playerData['pts_ppr'] ?? $playerData['pts_std'] ?? 0));
            if ($points > 0) {
                $playersWithPoints++;
            }
        }

        // If at least 10% of sampled players have projections, data is valid
        return ($playersWithPoints / $sampleSize) > 0.1;
    }

    /**
     * Get fallback ADP data
     */
    private function getFallbackAdpData(string $season, string $format, string $sport): array
    {
        // Try ESPN as fallback
        try {
            $espnPlayers = $this->espnSdk->getFantasyPlayers((int) $season, 'mDraftDetail', 1000, ttlSeconds: 0);

            if (!empty($espnPlayers) && is_array($espnPlayers) && count($espnPlayers) > 100) {
                return $this->convertEspnToAdpFormat($espnPlayers);
            }
        } catch (\Exception $e) {
            Log::warning("ESPN fallback ADP failed", ['error' => $e->getMessage()]);
        }

        // Try external API as last resort
        try {
            return $this->getExternalAdpData($season);
        } catch (\Exception $e) {
            Log::warning("External ADP fallback failed", ['error' => $e->getMessage()]);
        }

        return [];
    }

    /**
     * Get fallback projections data
     */
    private function getFallbackProjections(string $season, int $week, string $sport): array
    {
        // For now, return empty - we could implement external projections API here
        // This is a placeholder for future enhancement
        Log::info("No projections fallback implemented yet");
        return [];
    }

    /**
     * Convert ESPN data to ADP format
     */
    private function convertEspnToAdpFormat(array $espnPlayers): array
    {
        $adpData = [];
        $rank = 1;

        // Sort ESPN players by ADP
        $playersWithAdp = [];
        foreach ($espnPlayers as $player) {
            $adp = $player['averageDraftPosition'] ?? $player['draftRanksByRankType']['PPR']['rank'] ?? null;
            if ($adp && is_numeric($adp)) {
                $playersWithAdp[] = [
                    'player' => $player,
                    'adp' => (float) $adp,
                ];
            }
        }

        usort($playersWithAdp, fn($a, $b) => $a['adp'] <=> $b['adp']);

        foreach ($playersWithAdp as $entry) {
            $player = $entry['player'];

            // Try to match ESPN player to Sleeper ID (simplified)
            // In a real implementation, you'd need a proper mapping table
            $playerId = $this->mapEspnToSleeperId($player);

            if ($playerId) {
                $adpData[] = [
                    'player_id' => (string) $playerId,
                    'adp' => (float) $entry['adp'],
                    'source' => 'espn_fallback',
                ];
            }

            $rank++;
            if (count($adpData) >= 500) { // Limit to reasonable size
                break;
            }
        }

        return $adpData;
    }

    /**
     * Get external ADP data from a reliable source
     */
    private function getExternalAdpData(string $season): array
    {
        // This is a placeholder for integrating with external ADP sources
        // In a real implementation, you might use:
        // - FantasyPros API
        // - Underdog API
        // - Custom ADP aggregation service

        Log::info("External ADP API not implemented - returning empty");
        return [];
    }

    /**
     * Generate synthetic ADP for when all sources fail
     */
    private function generateSyntheticAdp(string $season, string $sport): array
    {
        $syntheticGenerator = app(SyntheticAdpGenerator::class);

        // Try consensus-based ADP first
        $consensusAdp = $syntheticGenerator->generateConsensusAdp($season, $sport);
        if (!empty($consensusAdp)) {
            return $consensusAdp;
        }

        // Fallback to heuristic-based ADP
        return $syntheticGenerator->generateHeuristicAdp($season, $this->sleeperSdk, $sport);
    }

    /**
     * Map ESPN player ID to Sleeper player ID
     */
    private function mapEspnToSleeperId(array $espnPlayer): ?string
    {
        // This is a simplified mapping - in practice you'd need a comprehensive mapping table
        // For now, return null to indicate no mapping available
        return null;
    }
}
