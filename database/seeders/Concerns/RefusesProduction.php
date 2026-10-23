<?php

namespace Database\Seeders\Concerns;

use RuntimeException;

/**
 * For seeders that write demo or test accounts: a seeder run by name
 * (`db:seed --class=... --force`) skips DatabaseSeeder's own guard, so each
 * such seeder refuses production itself, before it writes anything.
 */
trait RefusesProduction
{
    protected function refuseInProduction(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Refusing to run '.class_basename($this).' in production: it writes demo or test accounts.');
        }
    }
}
