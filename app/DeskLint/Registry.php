<?php

namespace App\DeskLint;

use App\DeskLint\Rules\ActiveBonusOnClosedCasino;
use App\DeskLint\Rules\ClockBrandOnBlacklisted;
use App\DeskLint\Rules\CurrencyMismatch;
use App\DeskLint\Rules\CurrencyUnknown;
use App\DeskLint\Rules\DuplicateWelcome;
use App\DeskLint\Rules\EndedButActive;
use App\DeskLint\Rules\MissingLink;
use App\DeskLint\Rules\OrphanBonus;
use App\DeskLint\Rules\PackageLegWithoutCap;
use App\DeskLint\Rules\StaleActive;

final class Registry
{
    /** @return list<class-string<Rule>> */
    public static function classes(): array
    {
        return [
            CurrencyMismatch::class,
            CurrencyUnknown::class,
            DuplicateWelcome::class,
            EndedButActive::class,
            StaleActive::class,
            MissingLink::class,
            OrphanBonus::class,
            ActiveBonusOnClosedCasino::class,
            ClockBrandOnBlacklisted::class,
            PackageLegWithoutCap::class,
        ];
    }

    /** @return list<Rule> */
    public static function rules(?array $only = null): array
    {
        $rules = array_map(fn (string $class) => new $class(), self::classes());

        if ($only) {
            $rules = array_values(array_filter($rules, fn (Rule $rule) => in_array($rule->id(), $only, true)));
        }

        return $rules;
    }
}
