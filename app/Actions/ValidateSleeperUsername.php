<?php

namespace App\Actions;

use Illuminate\Support\Facades\Log;
use MichaelCrowcroft\SleeperLaravel\Facades\Sleeper;

class ValidateSleeperUsername
{
    /**
     * Validate a Sleeper username and return user information.
     *
     * @param  string  $username  The Sleeper username to validate
     * @return array|null Returns ['user_id' => string, 'username' => string] if valid, null if invalid
     */
    public function execute(string $username): ?array
    {
        try {
            $response = Sleeper::users()->get($username);

            // Check if the response was successful (status code 200-299)
            if (! $response->successful()) {
                return null;
            }

            $userData = $response->json();

            // Check if we got valid user data with a user_id
            if (is_array($userData) && isset($userData['user_id']) && ! empty($userData['user_id'])) {
                return [
                    'user_id' => (string) $userData['user_id'],
                    'username' => $userData['username'] ?? $username,
                ];
            }

            return null;
        } catch (\Exception $e) {
            // Log the error but don't expose it to the user
            Log::warning('Failed to validate Sleeper username', [
                'username' => $username,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
