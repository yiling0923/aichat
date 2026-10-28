<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class MakePlatformOwner extends Command
{
    protected $signature = 'aichat:make-platform-owner {email} {--name=Platform Owner} {--password=}';

    protected $description = 'Create (or reset) the platform owner login that opens the /platform console';

    public function handle(): int
    {
        $password = (string) ($this->option('password') ?: $this->secret('Choose a password (at least 8 characters)'));

        if (strlen($password) < 8) {
            $this->error('The password needs at least 8 characters.');

            return self::FAILURE;
        }

        $user = User::query()->firstOrNew(['email' => $this->argument('email')]);
        abort_if($user->exists && $user->business_id !== null, 1, 'That email already belongs to a business account.');

        $user->forceFill(['name' => $this->option('name'), 'password' => $password, 'role' => 'platform_owner', 'active' => true, 'email_verified_at' => now()])->save();
        $this->info('Platform owner ready: sign in at /login.');

        return self::SUCCESS;
    }
}
