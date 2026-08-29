<?php

namespace App\Http\Controllers\Concerns;

/**
 * The "expiring soon" custom-window default day count, shared by the
 * dashboard's custom-window card (BuildsDashboardStats) and
 * Admin\ProductController's expiring-soon column so the two can't drift
 * apart. PHP doesn't allow accessing a trait's own constant via
 * `TraitName::CONST` outside a class that composes the trait, so this lives
 * on a plain class instead — both a trait and a controller can reference it.
 */
final class ExpiringSoonDefaults
{
    public const int CUSTOM_WINDOW_DAYS = 45;
}
