<?php

namespace App\Enums;

enum CellLogFlagReason: string
{
    case RapidActions = 'rapid_actions';
    case OffHours = 'off_hours';
    case QuickFlip = 'quick_flip';
}
