<?php

namespace App\Enums;

/**
 * Where a trip row action returns: the all-trips or today's-trips list (one
 * Inertia page) or the retained Blade map picker, whose truck cards delete a
 * truck's open trip. The value is the `list` parameter each action sends.
 */
enum StoreTripList: string
{
    case All = 'all';
    case Today = 'today';
    case Map = 'map';
}
