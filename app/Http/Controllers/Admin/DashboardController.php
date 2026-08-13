<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CellLogAction;
use App\Enums\CellState;
use App\Http\Controllers\Controller;
use App\Models\Cell;
use App\Models\CellStatusLog;
use App\Models\Pallet;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * How many days ahead of today counts as "expiring soon" on the dashboard.
     */
    private const int EXPIRING_SOON_DAYS = 7;

    public function index(): Response
    {
        $today = today();
        $expiringSoonUntil = $today->copy()->addDays(self::EXPIRING_SOON_DAYS);

        $occupancy = Cell::query()->select('state')->selectRaw('count(*) as count')->groupBy('state')->pluck('count', 'state');
        $activityToday = CellStatusLog::query()->select('action')->selectRaw('count(*) as count')->whereDate('created_at', $today)->groupBy('action')->pluck('count', 'action');

        return Inertia::render('Admin/Dashboard/Index', [
            'stats' => [
                'occupancy' => [
                    'empty' => (int) ($occupancy[CellState::Empty->value] ?? 0),
                    'full' => (int) ($occupancy[CellState::Full->value] ?? 0),
                    'opened' => (int) ($occupancy[CellState::Opened->value] ?? 0),
                ],
                'expiring' => [
                    'expired' => Pallet::query()->whereDate('expiration_date', '<', $today)->count(),
                    'soon' => Pallet::query()->whereBetween('expiration_date', [$today, $expiringSoonUntil])->count(),
                ],
                'activity_today' => [
                    'stored' => (int) ($activityToday[CellLogAction::Stored->value] ?? 0),
                    'opened' => (int) ($activityToday[CellLogAction::Opened->value] ?? 0),
                    'emptied' => (int) ($activityToday[CellLogAction::Emptied->value] ?? 0),
                    'transferred' => (int) ($activityToday[CellLogAction::TransferredOut->value] ?? 0) + (int) ($activityToday[CellLogAction::TransferredIn->value] ?? 0),
                ],
            ],
            'today' => $today->toDateString(),
            'expiringSoonUntil' => $expiringSoonUntil->toDateString(),
        ]);
    }
}
