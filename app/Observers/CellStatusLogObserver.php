<?php

namespace App\Observers;

use App\Enums\CellLogAction;
use App\Enums\CellLogFlagReason;
use App\Models\CellStatusLog;
use App\Services\DashboardStatsCache;

/**
 * Evaluates the rule-based auto-flagging thresholds (config/cell_status_log_flags.php)
 * against every newly created cell status log, regardless of which mobile-user action
 * (store/open/empty/transfer) produced it — CellStatusLog::create() is the single choke
 * point for all of them (see PalletController::logCellStatus()). This is also the single
 * choke point for every occupancy/activity-changing pallet action, so it doubles as the
 * dashboard stats cache's invalidation trigger (see DashboardStatsCache, RowObserver).
 */
class CellStatusLogObserver
{
    public function created(CellStatusLog $log): void
    {
        foreach ($this->matchingReasons($log) as $reason) {
            $log->flags()->create(['reason' => $reason]);
        }

        DashboardStatsCache::flush();
    }

    /**
     * @return list<CellLogFlagReason>
     */
    private function matchingReasons(CellStatusLog $log): array
    {
        $reasons = [];

        if ($this->isRapidActions($log)) {
            $reasons[] = CellLogFlagReason::RapidActions;
        }

        if ($this->isOffHours($log)) {
            $reasons[] = CellLogFlagReason::OffHours;
        }

        if ($this->isQuickFlip($log)) {
            $reasons[] = CellLogFlagReason::QuickFlip;
        }

        return $reasons;
    }

    /**
     * More than the configured threshold of actions by the same user within a
     * rolling window ending at this log's own timestamp.
     */
    private function isRapidActions(CellStatusLog $log): bool
    {
        $threshold = config('cell_status_log_flags.rapid_actions.threshold');
        $windowMinutes = config('cell_status_log_flags.rapid_actions.window_minutes');

        $count = CellStatusLog::query()
            ->where('user_id', $log->user_id)
            ->where('created_at', '>=', $log->created_at->subMinutes($windowMinutes))
            ->count();

        return $count > $threshold;
    }

    /**
     * Outside the configured working-hours window, in the application's timezone.
     */
    private function isOffHours(CellStatusLog $log): bool
    {
        $start = $log->created_at->setTimeFromTimeString(config('cell_status_log_flags.off_hours.start'));
        $end = $log->created_at->setTimeFromTimeString(config('cell_status_log_flags.off_hours.end'));

        return $log->created_at->lt($start) || $log->created_at->gte($end);
    }

    /**
     * This log is an `emptied` action, and the same user stored a pallet in the
     * same cell within the configured window beforehand.
     */
    private function isQuickFlip(CellStatusLog $log): bool
    {
        if ($log->action !== CellLogAction::Emptied) {
            return false;
        }

        $windowMinutes = config('cell_status_log_flags.quick_flip.window_minutes');

        return CellStatusLog::query()
            ->where('user_id', $log->user_id)
            ->where('cell_id', $log->cell_id)
            ->where('action', CellLogAction::Stored)
            ->where('created_at', '>=', $log->created_at->subMinutes($windowMinutes))
            ->exists();
    }
}
