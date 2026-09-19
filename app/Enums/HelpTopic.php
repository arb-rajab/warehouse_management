<?php

namespace App\Enums;

enum HelpTopic: string
{
    case Dashboard = 'dashboard';
    case Rows = 'rows';
    case Cells = 'cells';
    case Users = 'users';
    case Products = 'products';
    case CellLogs = 'cell-logs';
    case CellVerificationRounds = 'cell-verification-rounds';
}
