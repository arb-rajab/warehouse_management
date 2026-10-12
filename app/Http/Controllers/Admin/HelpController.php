<?php

namespace App\Http\Controllers\Admin;

use App\Enums\HelpTopic;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class HelpController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Help/Index');
    }

    public function show(HelpTopic $topic): Response
    {
        $component = match ($topic) {
            HelpTopic::Dashboard => 'Dashboard',
            HelpTopic::Rows => 'Rows',
            HelpTopic::Cells => 'Cells',
            HelpTopic::Users => 'Users',
            HelpTopic::Products => 'Products',
            HelpTopic::CellLogs => 'CellLogs',
            HelpTopic::CellVerificationRounds => 'CellVerificationRounds',
            HelpTopic::Settings => 'Settings',
        };

        return Inertia::render("Admin/Help/{$component}");
    }
}
