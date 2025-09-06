<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Log;
use MichaelCrowcroft\SleeperLaravel\Facades\Sleeper;

class ValidateSleeperUsername implements ValidationRule
{
    private ?array $userData = null;

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            $response = Sleeper::users()->get($value);

            // Check if the response was successful (status code 200-299)
            if (! $response->successful()) {
                Log::info('Sleeper API returned unsuccessful response', [
                    'username' => $value,
                    'status' => $response->status(),
                    'response_body' => $response->body(),
                ]);
                $fail('This Sleeper username does not exist. Please check your username and try again.');
                return;
            }

            $userData = $response->json();

            // Check if we got valid user data with a user_id
            if (is_array($userData) && isset($userData['user_id']) && ! empty($userData['user_id'])) {
                $this->userData = [
                    'user_id' => (string) $userData['user_id'],
                    'username' => $userData['username'] ?? $value,
                ];
                return;
            }

            // Log when we get a successful response but no valid user data
            Log::info('Sleeper API returned successful response but no valid user data', [
                'username' => $value,
                'user_data' => $userData,
            ]);

            $fail('This Sleeper username does not exist. Please check your username and try again.');
        } catch (\Exception $e) {
            // Log the error but don't expose it to the user
            Log::warning('Failed to validate Sleeper username', [
                'username' => $value,
                'error' => $e->getMessage(),
                'exception_type' => get_class($e),
            ]);

            $fail('This Sleeper username does not exist. Please check your username and try again.');
        }
    }

    /**
     * Get the validated user data.
     */
    public function getUserData(): ?array
    {
        return $this->userData;
    }
}
