<?php

namespace App\Enums;

enum CheckpointStatus: string
{
    case Pending = 'pending';
    case Delivered = 'delivered';
    case HasIssue = 'has_issue';
}
