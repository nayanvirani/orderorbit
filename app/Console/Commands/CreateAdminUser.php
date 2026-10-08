<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Creates (or resets) an Internal Admin account and prints a one-time password.
 */
class CreateAdminUser extends Command
{
    protected $signature = 'orderorbit:admin-user {email} {--name=Growvia team}';

    protected $description = 'Create or reset an Internal Admin account';

    public function handle(): int
    {
        $email = strtolower(trim($this->argument('email')));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('That isn\'t a valid email address.');

            return self::FAILURE;
        }
        $password = Str::password(20, symbols: false);
        $user = User::firstOrNew(['email' => $email]);
        $user->forceFill(['name' => $user->name ?: $this->option('name'), 'password' => $password, 'is_admin' => true])->save();
        $this->info(($user->wasRecentlyCreated ? 'Created' : 'Reset').' admin '.$email);
        $this->line('Password (shown once): '.$password);
        $this->line('Sign in at '.route('admin.login'));

        return self::SUCCESS;
    }
}
