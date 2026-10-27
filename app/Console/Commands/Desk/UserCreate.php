<?php

namespace App\Console\Commands\Desk;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * php artisan fec:user:create davey@example.com --name="Davey" --joomla-id=42 --role=admin
 * The password is asked blind (never on the command line, never in the shell history).
 */
class UserCreate extends Command
{
    protected $signature = 'fec:user:create {email : Login email, also where the one-time codes go}
                            {--name= : Display name (default: the part before @)}
                            {--joomla-id= : The Joomla user id the legacy desks attribute edits to (42 = desk owner)}
                            {--role=editor : admin or editor}';

    protected $description = 'Create a redazione account for the v3 desks';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Not an email address: '.$email);

            return self::INVALID;
        }
        if (User::query()->where('email', $email)->exists()) {
            $this->error('An account with this email exists already (fec:user:password to change its password).');

            return self::FAILURE;
        }
        $role = (string) $this->option('role');
        if (! in_array($role, ['admin', 'editor'], true)) {
            $this->error('Role must be admin or editor');

            return self::INVALID;
        }

        $password = (string) $this->secret('Password (min 12 characters, typed blind)');
        if (strlen($password) < 12) {
            $this->error('Too short: 12 characters at least.');

            return self::INVALID;
        }
        if ($password !== (string) $this->secret('Repeat it')) {
            $this->error('The two passwords differ.');

            return self::INVALID;
        }

        $user = User::create([
            'name' => (string) ($this->option('name') ?: strstr($email, '@', true)),
            'email' => $email,
            'password' => $password,
            'joomla_user_id' => $this->option('joomla-id') !== null ? (int) $this->option('joomla-id') : null,
            'role' => $role,
        ]);
        $this->info(sprintf('Created #%d %s <%s> role=%s joomla_user_id=%s', $user->id, $user->name, $user->email, $user->role, $user->joomla_user_id ?? '-'));

        return self::SUCCESS;
    }
}
