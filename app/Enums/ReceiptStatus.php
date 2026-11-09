<?php

namespace App\Enums;

enum ReceiptStatus: string
{
    case Draft = 'draft';
    case Finalized = 'finalized';
}
