<?php

namespace App\Enums;

enum CellState: string
{
    case Empty = 'empty';
    case Full = 'full';
    case Opened = 'opened';
}
