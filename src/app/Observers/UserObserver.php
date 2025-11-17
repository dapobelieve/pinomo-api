<?php

namespace App\Observers;

use App\Models\User;
use App\Models\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserObserver
{
    public function created(User $user): void
    {
        $this->createClientForUser($user);
    }

    private function createClientForUser(User $user): void
    {
        try {
            DB::transaction(function () use ($user) {
                $names = $this->parseName($user->name);

                Client::create([
                    'external_id' => $user->id,
                    'client_type' => 'individual',
                    'first_name' => $names['first_name'],
                    'last_name' => $names['last_name'],
                    'email' => $user->email,
                    'kyc_status' => 'pending',
                    'date_of_birth' => null,
                    'gender' => null,
                    'nationality' => null,
                    'phone_number' => null,
                    'address' => null,
                ]);
            });
        } catch (\Exception $e) {
            Log::error('Failed to create client for user', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function parseName(string $name): array
    {
        $parts = explode(' ', trim($name), 2);

        return [
            'first_name' => $parts[0] ?? 'Unknown',
            'last_name' => $parts[1] ?? 'User',
        ];
    }
}
