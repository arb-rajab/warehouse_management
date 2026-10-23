<?php

namespace App\Support;

/** Exact decimal display, without a float conversion that would lose large amounts. */
class ProductFormNumber
{
    public static function format(string|int|float|null $value): string
    {
        $number = (string) ($value ?? 0);

        return str_contains($number, '.') ? (rtrim(rtrim($number, '0'), '.') ?: '0') : $number;
    }
}
