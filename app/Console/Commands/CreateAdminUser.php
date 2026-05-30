<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\Console\Exception\MissingInputException;

class CreateAdminUser extends Command
{
    protected $signature = 'admin:create
                            {--email= : Email address for the admin account}
                            {--name= : Display name for the admin account}
                            {--password= : Password for the admin account (avoid shell history)}
                            {--promote : Promote an existing non-admin user with the same email}';

    protected $description = 'Create the first admin account (or promote an existing user)';

    public function handle(): int
    {
        $interactive = $this->input->isInteractive();

        $email = trim(strtolower((string) ($this->option('email') ?? '')));
        $name = trim((string) ($this->option('name') ?? ''));
        $password = (string) ($this->option('password') ?? '');

        if ($email === '') {
            if (!$interactive) {
                $this->error('Missing required option --email in non-interactive mode.');
                return self::FAILURE;
            }

            try {
                $email = trim(strtolower((string) $this->ask('Admin email')));
            } catch (MissingInputException) {
                $this->error('Missing required option --email in non-interactive mode.');
                return self::FAILURE;
            }
        }

        if ($name === '') {
            if (!$interactive) {
                $name = 'Administrator';
            } else {
                try {
                    $name = trim((string) $this->ask('Admin name', 'Administrator'));
                } catch (MissingInputException) {
                    $name = 'Administrator';
                }
            }
        }

        if ($password === '') {
            if (!$interactive) {
                $this->error('Missing required option --password in non-interactive mode.');
                return self::FAILURE;
            }

            try {
                $password = (string) $this->secret('Admin password');
                $confirmation = (string) $this->secret('Confirm admin password');
            } catch (MissingInputException) {
                $this->error('Missing required option --password in non-interactive mode.');
                return self::FAILURE;
            }

            if ($password !== $confirmation) {
                $this->error('Password confirmation does not match.');
                return self::FAILURE;
            }
        }

        $validator = Validator::make(
            [
                'email' => $email,
                'name' => $name,
                'password' => $password,
            ],
            [
                'email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
                'name' => ['required', 'string', 'max:255'],
                'password' => ['required', Password::defaults()],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $existing = User::query()->where('email', $email)->first();

        if ($existing !== null) {
            if ($existing->is_admin) {
                $this->error("An admin user already exists for {$email}. No changes were made.");
                $this->line('Change the password from Profile after login, or use:');
                $this->line("php artisan admin:fix-access {$email} --password=\"new-strong-password\"");
                return self::FAILURE;
            }

            $shouldPromote = (bool) $this->option('promote');

            if (!$shouldPromote && $interactive) {
                try {
                    $shouldPromote = $this->confirm(
                        "A non-admin user with {$email} already exists. Promote this account to admin and set a new password?",
                        false
                    );
                } catch (MissingInputException) {
                    $shouldPromote = false;
                }
            }

            if (!$shouldPromote) {
                $this->error('User exists but is not admin. Re-run with --promote to elevate this account.');
                return self::FAILURE;
            }

            $existing->name = $name;
            $existing->password = Hash::make($password);
            $existing->is_admin = true;
            if ($existing->email_verified_at === null) {
                $existing->email_verified_at = now();
            }
            $existing->save();

            $this->info('Existing user promoted to admin.');
            $this->line("Email: {$existing->email}");
            return self::SUCCESS;
        }

        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'is_admin' => true,
        ]);
        $user->email_verified_at = now();
        $user->save();

        $this->info('Admin user created successfully.');
        $this->line("Email: {$user->email}");
        $this->line('You can now sign in and manage the site from /admin.');

        return self::SUCCESS;
    }
}
