<?php

namespace App\Fec\Legacy\Joomla;

/** JLanguage: the site is English only; getTag() is what the templates read. */
class Language
{
    public function getTag(): string
    {
        return 'en-GB';
    }

    public function isRTL(): bool
    {
        return false;
    }

    public function load(string $extension = 'joomla', ?string $basePath = null): bool
    {
        return true;
    }
}
