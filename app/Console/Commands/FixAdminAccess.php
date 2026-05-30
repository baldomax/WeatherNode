<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class FixAdminAccess extends Command
{
    protected $signature = 'admin:fix-access {email} {--password=} {--force-admin}';

    protected $description = 'Fix admin access by ensuring is_admin flag is set and optionally reset password';

    public function handle()
    {
        $email = $this->argument('email');
        $password = $this->option('password');
        $forceAdmin = $this->option('force-admin');

        // Find the user
        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("User with email '{$email}' not found.");
            return 1;
        }

        $this->info("Found user: {$user->name} ({$user->email})");
        $this->info("Current is_admin status: " . ($user->is_admin ? 'true' : 'false'));

        // Update is_admin if needed
        if (!$user->is_admin || $forceAdmin) {
            $user->is_admin = true;
            $this->info("Setting is_admin to true...");
        }

        // Update password if provided
        if ($password) {
            $user->password = Hash::make($password);
            $this->info("Password has been reset.");
        }

        // Save changes
        $user->save();

        $this->info("\n✅ Admin access fixed!");
        $this->info("User: {$user->email}");
        $this->info("is_admin: " . ($user->is_admin ? 'true' : 'false'));
        
        if ($password) {
            $this->warn("\n⚠️  New password has been set. Please log in with the new password.");
        } else {
            $this->info("\nℹ️  Password was not changed. If you're having login issues, use --password option.");
        }

        return 0;
    }
}
