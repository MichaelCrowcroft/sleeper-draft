<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ApiTokenController extends Controller
{
    /**
     * Display API tokens management page
     */
    public function index(): Response
    {
        $user = Auth::user();

        $tokens = $user->tokens()
            ->where('name', 'LIKE', 'MCP%')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($token) {
                return [
                    'id' => $token->id,
                    'name' => $token->name,
                    'last_used_at' => $token->last_used_at,
                    'created_at' => $token->created_at,
                    'abilities' => $token->abilities,
                    'token_preview' => substr($token->token, 0, 8).'...'.substr($token->token, -8),
                ];
            });

        return Inertia::render('settings/ApiTokens', [
            'tokens' => $tokens,
            'newToken' => session('new_token'),
        ]);
    }

    /**
     * Generate a new API token
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $user = Auth::user();

        // Limit the number of tokens per user
        $existingTokensCount = $user->tokens()
            ->where('name', 'LIKE', 'MCP%')
            ->count();

        if ($existingTokensCount >= 10) {
            throw ValidationException::withMessages([
                'name' => ['You can only have up to 10 active MCP tokens.'],
            ]);
        }

        $tokenName = 'MCP: '.$request->name;

        // Check if token name already exists
        $existingToken = $user->tokens()
            ->where('name', $tokenName)
            ->first();

        if ($existingToken) {
            throw ValidationException::withMessages([
                'name' => ['A token with this name already exists.'],
            ]);
        }

        $token = $user->createToken($tokenName, ['mcp:access']);

        // Store the token in session to display once
        session()->flash('new_token', [
            'name' => $tokenName,
            'token' => $token->plainTextToken,
        ]);

        return redirect()->back()->with('success', 'API token created successfully!');
    }

    /**
     * Revoke an API token
     */
    public function destroy(Request $request, $tokenId): JsonResponse
    {
        $user = Auth::user();

        $token = $user->tokens()
            ->where('id', $tokenId)
            ->where('name', 'LIKE', 'MCP%')
            ->first();

        if (! $token) {
            return response()->json(['message' => 'Token not found'], 404);
        }

        $token->delete();

        return response()->json(['message' => 'Token revoked successfully']);
    }

    /**
     * Show full token
     */
    public function show(Request $request, $tokenId): JsonResponse
    {
        $user = Auth::user();

        $token = $user->tokens()
            ->where('id', $tokenId)
            ->where('name', 'LIKE', 'MCP%')
            ->first();

        if (! $token) {
            return response()->json(['message' => 'Token not found'], 404);
        }

        return response()->json([
            'token' => $token->token,
        ]);
    }

    /**
     * Revoke all API tokens
     */
    public function destroyAll(): JsonResponse
    {
        $user = Auth::user();

        $deletedCount = $user->tokens()
            ->where('name', 'LIKE', 'MCP%')
            ->delete();

        return response()->json([
            'message' => "Successfully revoked {$deletedCount} token(s)",
            'deleted_count' => $deletedCount,
        ]);
    }
}
