<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * A member of the redazione. joomla_user_id is the id the ported desks see
 * through JFactory::getUser() (42 for the desk owner on the live site);
 * role is "admin" or "editor" (the desks' own gates decide what each id may do).
 */
#[Fillable(['name', 'email', 'password', 'joomla_user_id', 'role'])]
#[Hidden(['password', 'remember_token', 'login_code'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'joomla_user_id' => 'integer',
            'login_code_expires_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
}
