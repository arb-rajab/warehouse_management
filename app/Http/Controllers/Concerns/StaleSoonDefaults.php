<?php

namespace App\Http\Controllers\Concerns;

/**
 * The dashboard's "stale within days" custom-window default day count. This is
 * a controller-level UI default only — `Pallet::isStaleAfter()` itself stays
 * parameter-only with no model-level constant (see the "no fixed threshold"
 * note in .ai/rules/models.md), the same way ExpiringSoonDefaults' default
 * exists alongside a Pallet model that has no expiring-related constant of
 * its own.
 */
final class StaleSoonDefaults
{
    /**
     * Deliberately below PalletFactory::stale()'s fixed 30-day backdate (see
     * .ai/rules/factories.md) so a `Pallet::factory()->stale()` fixture reads
     * as stale under this default without landing exactly on its boundary.
     */
    public const int CUSTOM_WINDOW_DAYS = 21;
}
