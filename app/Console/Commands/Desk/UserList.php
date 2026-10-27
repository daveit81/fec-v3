<?php

namespace App\Console\Commands\Desk;

use App\Models\User;
use Illuminate\Console\Command;

class UserList extends Command
{
    protected $signature = 'fec:user:list';

    protected $description = 'List the redazione accounts';

    public function handle(): int
    {
        $rows = User::query()->orderBy('id')->get()->map(fn (User $u) => [
            $u->id, $u->name, $u->email, $u->role, $u->joomla_user_id ?? '-', $u->last_login_at?->timezone(config('fec.timezone'))->format('d/m/Y H:i') ?? 'never',
        ])->all();
        $this->table(['id', 'name', 'email', 'role', 'joomla id', 'last login'], $rows);

        return self::SUCCESS;
    }
}
