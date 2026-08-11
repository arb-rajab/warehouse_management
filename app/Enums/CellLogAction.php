<?php

namespace App\Enums;

enum CellLogAction: string
{
    case Stored = 'stored';
    case Opened = 'opened';
    case Emptied = 'emptied';
    case TransferredOut = 'transferred_out';
    case TransferredIn = 'transferred_in';
}
