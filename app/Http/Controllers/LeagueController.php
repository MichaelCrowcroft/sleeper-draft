<?php

namespace App\Http\Controllers;

use App\Actions\Sleeper\SyncUserLeaguesAction;
use App\Models\League;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeagueController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $leagues = League::query()
            ->where('user_id', $user->id)
            ->withCount('rosters')
            ->orderByDesc('season')
            ->orderBy('name')
            ->get();

        return Inertia::render('leagues/Index', [
            'leagues' => $leagues,
            'hasSleeperAccount' => ! empty($user->sleeper_user_id),
        ]);
    }

    public function sync(Request $request, SyncUserLeaguesAction $syncUserLeaguesAction): RedirectResponse
    {
        $user = $request->user();

        if (empty($user->sleeper_user_id)) {
            return back()->withErrors(['message' => 'Please connect your Sleeper account first in Settings.']);
        }

        try {
            $result = $syncUserLeaguesAction->execute($user, force: true);

            $leagueCount = $result['leagues'];
            $rosterCount = $result['rosters'];

            if ($leagueCount === 0) {
                $message = 'No leagues found for the current season.';
            } else {
                $message = $leagueCount === 1
                    ? "Successfully updated 1 league and {$rosterCount} rosters."
                    : "Successfully updated {$leagueCount} leagues and {$rosterCount} rosters.";
            }

            return back()->with('message', $message);

        } catch (\Throwable $e) {
            return back()->withErrors(['message' => 'Failed to sync leagues: '.$e->getMessage()]);
        }
    }

    public function show(Request $request, League $league): Response
    {
        $user = $request->user();

        // Ensure the user owns this league
        if ($league->user_id !== $user->id) {
            abort(404);
        }

        $league->load('rosters');

        return Inertia::render('leagues/Show', [
            'league' => $league,
        ]);
    }
}
