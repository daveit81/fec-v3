<?php

namespace App\Console\Commands\Desk;

use App\Models\User;
use Illuminate\Console\Command;

class UserPassword extends Command
{
    protected $signature = 'fec:user:password {email}';

    protected $description = 'Set a new password for a redazione account (asked blind)';

    public function handle(): int
    {
        $user = User::query()->where('email', strtolower(trim((string) $this->argument('email'))))->first();
        if ($user === null) {
            $this->error('No such account.');

            return self::FAILURE;
        }
        $password = (string) $this->secret('New password (min 12 characters, typed blind)');
        if (strlen($password) < 12 || $password !== (string) $this->secret('Repeat it')) {
            $this->error('Too short or the two passwords differ.');

            return self::INVALID;
        }
        $user->password = $password;
        $user->remember_token = null;
        $user->save();
        $this->info('Password changed for '.$user->email.'; existing sessions will have to log in again.');

        return self::SUCCESS;
    }
}
