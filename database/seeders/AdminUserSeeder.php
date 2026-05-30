<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Optionally seed the first admin user from env vars.
     *
     * Behavior:
     * - If an admin already exists, do nothing.
     * - If no admin exists and ADMIN_EMAIL + ADMIN_PASSWORD are configured, create one.
     * - If no admin exists and credentials are missing, do nothing (use `php artisan admin:create`).
     */
    public function run(): void
    {
        if (User::query()->where('is_admin', true)->exists()) {
            return;
        }

        $email = trim((string) env('ADMIN_EMAIL', ''));
        $password = (string) env('ADMIN_PASSWORD', '');

        if ($email === '' || $password === '') {
            return;
        }

        $user = User::updateOrCreate(
            ['email' => strtolower($email)],
            [
                'name' => 'Administrator',
                'password' => Hash::make($password),
                'is_admin' => true,
            ]
        );

        // Explicitly ensure is_admin is true (in case user existed but flag was reset)
        if (!$user->is_admin) {
            $user->is_admin = true;
        }

        if ($user->email_verified_at === null) {
            $user->email_verified_at = now();
        }

        $user->save();
    }
}
