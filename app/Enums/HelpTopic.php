<?php

namespace App\Enums;

enum HelpTopic: string
{
    case Dashboard = 'dashboard';
    case Rows = 'rows';
    case Cells = 'cells';
    case WarehouseMap = 'warehouse-map';
    case Users = 'users';
    case Products = 'products';
    case CellLogs = 'cell-logs';
    case CellVerificationRounds = 'cell-verification-rounds';
    case Settings = 'settings';
}
