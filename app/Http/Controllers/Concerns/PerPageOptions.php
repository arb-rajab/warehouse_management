<?php

namespace App\Http\Controllers\Concerns;

/**
 * The page-size options offered by every admin table's "items per page"
 * selector, shared by the base Controller's resolvePerPage() and
 * Requests\Concerns\FiltersPerPage so the two can't drift apart. PHP doesn't
 * allow accessing a trait's own constant via `TraitName::CONST` outside a
 * class that composes the trait, so this lives on a plain class instead —
 * both a controller and a request can reference it (same pattern as
 * ExpiringSoonDefaults).
 */
final class PerPageOptions
{
    /**
     * @var list<int>
     */
    public const array VALUES = [10, 20, 25, 50, 100];
}
