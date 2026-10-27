<?php

namespace App\Fec\Legacy\Joomla;

/**
 * JUser as our code reads it: id, guest, name, username, email, get(),
 * authorise(). Public pages run as guest; the desk views will map the v3
 * user to its Joomla id (fec.legacy.desk_user_id, 42 on the live site)
 * once the redazione login exists in v3.
 */
class User
{
    public int $id = 0;

    public int $guest = 1;

    public string $name = '';

    public string $username = '';

    public string $email = '';

    public static function current(): static
    {
        $user = new static;
        $authenticated = auth()->check() ? auth()->user() : null;
        if ($authenticated !== null) {
            $user->id = (int) ($authenticated->joomla_user_id ?? config('fec.legacy.desk_user_id', 42));
            $user->guest = 0;
            $user->name = (string) ($authenticated->name ?? '');
            $user->username = (string) ($authenticated->email ?? '');
            $user->email = (string) ($authenticated->email ?? '');
        }

        return $user;
    }

    public static function load(int $id): static
    {
        $user = new static;
        $user->id = $id;
        $user->guest = $id > 0 ? 0 : 1;

        return $user;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return property_exists($this, $key) ? $this->{$key} : $default;
    }

    public function authorise(string $action, ?string $asset = null): bool
    {
        return $this->guest === 0;
    }

    public function authorize(string $action, ?string $asset = null): bool
    {
        return $this->authorise($action, $asset);
    }

    /** @return list<int> */
    public function getAuthorisedViewLevels(): array
    {
        return $this->guest ? [1] : [1, 2];
    }
}
