<?php

namespace App\Console\Concerns;

use InvalidArgumentException;

/**
 * Reads a single text value from a command argument or option. Since Laravel
 * 13.40, `argument()` and `option()` are typed as anything a console input can
 * hold, arrays included; these narrow it to the one value the signature
 * declares and refuse an array (or a bare flag) instead of casting it.
 */
trait ReadsTextInput
{
    /**
     * The text of a required argument.
     */
    protected function textArgument(string $key): string
    {
        return $this->asText($this->argument($key), "The \"{$key}\" argument")
            ?? throw new InvalidArgumentException("The \"{$key}\" argument is required.");
    }

    /**
     * The text of an option that takes a value, or null when it was not given.
     */
    protected function textOption(string $key): ?string
    {
        return $this->asText($this->option($key), "The --{$key} option");
    }

    private function asText(mixed $value, string $name): ?string
    {
        return match (true) {
            $value === null => null,
            is_string($value) => $value,
            is_int($value), is_float($value) => (string) $value,
            default => throw new InvalidArgumentException("{$name} takes one text value."),
        };
    }
}
