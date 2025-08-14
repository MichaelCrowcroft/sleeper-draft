<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Sleeper\SyncUserLeaguesAction;
use App\Actions\Sleeper\SyncUserLeaguesWithClearAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\SleeperUpdateRequest;
use App\Services\SleeperSdk;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SleeperController extends Controller
{
    /**
     * Show the user's Sleeper settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Sleeper');
    }

    /**
     * Update the user's Sleeper settings.
     */
    public function update(
        SleeperUpdateRequest $request,
        SleeperSdk $sleeper,
        SyncUserLeaguesAction $syncUserLeaguesAction,
        SyncUserLeaguesWithClearAction $syncUserLeaguesWithClearAction
    ): RedirectResponse {
        $data = $request->validated();
        $username = $data['sleeper_username'];

        try {
            $sleeperUser = $sleeper->getUserByUsername($username);
            $sleeperUserId = $sleeperUser['user_id'] ?? null;
        } catch (\Throwable $e) {
            return back()->withErrors(['sleeper_username' => 'Unable to find Sleeper user for that username.'])->withInput();
        }

        $user = $request->user();
        $isUsernameChanged = $user->sleeper_username !== $username;

        $user->sleeper_username = $username;
        $user->sleeper_user_id = $sleeperUserId;
        $user->save();

        // Avoid external API calls in unit tests
        if (app()->runningUnitTests()) {
            return to_route('sleeper.edit')->with('message', 'Sleeper account updated successfully.');
        }

        // If username changed, clear old data and sync fresh data
        // If it's a new username, this ensures we get clean data from the new account
        try {
            if ($isUsernameChanged) {
                $result = $syncUserLeaguesWithClearAction->execute($user);
            } else {
                $result = $syncUserLeaguesAction->execute($user);
            }

            $leagueCount = $result['leagues'];
            $rosterCount = $result['rosters'];

            if ($leagueCount === 0) {
                $message = 'Sleeper account updated successfully. No leagues found for the current season.';
            } else {
                $message = "Sleeper account updated successfully. Synced {$leagueCount} leagues and {$rosterCount} rosters.";
            }

            return to_route('sleeper.edit')->with('message', $message);

        } catch (\Throwable $e) {
            // Don't block the save; surface a non-fatal validation message
            return back()->withErrors(['sleeper_username' => 'Saved username and ID, but failed to sync leagues: '.$e->getMessage()])->withInput();
        }
    }
}
