<?php

namespace App\Actions\Players;

use App\Models\Player;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class PlayerTableQuery
{
    /**
     * Build a base query for listing players with common filters and sorting.
     *
     * Supported options:
     * - search: string
     * - position: string
     * - team: string
     * - activeOnly: bool (default true)
     * - excludePlayerIds: array<string|int>
     * - sortBy: name|position|team|adp|age (default adp)
     * - sortDirection: asc|desc (default asc)
     */
    public function build(array $options = []): Builder
    {
        $search = (string) ($options['search'] ?? '');
        $position = (string) ($options['position'] ?? '');
        $team = (string) ($options['team'] ?? '');
        $activeOnly = array_key_exists('activeOnly', $options) ? (bool) $options['activeOnly'] : true;
        $excludePlayerIds = (array) ($options['excludePlayerIds'] ?? []);
        $sortBy = (string) ($options['sortBy'] ?? 'adp');
        $sortDirection = strtolower((string) ($options['sortDirection'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

        $query = Player::query()
            ->when($activeOnly, fn (Builder $q) => $q->active())
            ->search($search)
            ->position($position)
            ->team($team)
            ->excludePlayerIds($excludePlayerIds);

        // Sorting rules
        $this->applySorting($query, $sortBy, $sortDirection);

        return $query;
    }

    /**
     * Add eager loads typically needed for list views.
     */
    public function addListEagerLoads(Builder $query): Builder
    {
        return $query->with(['stats2024', 'projections2025']);
    }

    /**
     * Paginate a query with a sensible default per-page.
     */
    public function paginate(Builder $query, int $perPage = 25): LengthAwarePaginator
    {
        return $query->paginate($perPage);
    }

    private function applySorting(Builder $query, string $sortBy, string $direction): void
    {
        switch ($sortBy) {
            case 'name':
                $query->orderByName($direction);
                break;
            case 'position':
                $query->orderBy('position', $direction);
                break;
            case 'team':
                $query->orderBy('team', $direction);
                break;
            case 'age':
                $query->orderByAge($direction);
                break;
            case 'adp':
            default:
                $query->orderByAdp($direction);
                break;
        }
    }
}
