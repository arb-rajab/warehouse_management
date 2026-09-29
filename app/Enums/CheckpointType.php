<?php

namespace App\Enums;

enum CheckpointType: string
{
    case Delivery = 'delivery';
    case Other = 'other';
}
