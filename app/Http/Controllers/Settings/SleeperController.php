<?php

namespace App\Http\Controllers\Settings;

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
    public function update(SleeperUpdateRequest $request, SleeperSdk $sleeper): RedirectResponse
    {
        $data = $request->validated();

        $username = $data['sleeper_username'];

        try {
            $sleeperUser = $sleeper->getUserByUsername($username);
            $sleeperUserId = $sleeperUser['user_id'] ?? null;
        } catch (\Throwable $e) {
            return back()->withErrors(['sleeper_username' => 'Unable to find Sleeper user for that username.'])->withInput();
        }

        $user = $request->user();
        $user->sleeper_username = $username;
        $user->sleeper_user_id = $sleeperUserId;
        $user->save();

        return to_route('sleeper.edit');
    }
}
