<?php

namespace App\Enums;

enum ReceiptLineStatus: string
{
    case Pending = 'pending';
    case Matched = 'matched';
    case Mismatched = 'mismatched';
    case Extra = 'extra';
}
