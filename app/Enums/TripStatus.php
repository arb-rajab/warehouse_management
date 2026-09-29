<?php

namespace App\Enums;

enum TripStatus: string
{
    case Pending = 'pending';
    case Started = 'started';
    case Closed = 'closed';
}
