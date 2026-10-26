<?php

namespace App\Observers;

use App\Enums\CellLogAction;
use App\Enums\CellLogFlagReason;
use App\Models\CellStatusLog;
use App\Services\DashboardStatsCache;
use Illuminate\Support\Facades\DB;

/**
 * Evaluates the rule-based auto-flagging thresholds (config/cell_status_log_flags.php)
 * against every newly created cell status log, including pallet actions from
 * PalletActionService and cell activation changes from Admin\CellController.
 * Each log also triggers dashboard stats cache invalidation after commit;
 * PalletObserver covers pallet corrections that do not produce a log.
 *
 * The flags are written inside the caller's transaction, so they commit or roll back
 * together with the log they belong to. Only the cache flush waits for the commit:
 * flushed mid-transaction, a dashboard request landing before the commit would
 * recompute from the pre-commit data and cache that under the new version, leaving
 * the action invisible until the TTL ran out — and a rolled-back action needs no
 * flush at all. This is DB::afterCommit() rather than ShouldHandleEventsAfterCommit
 * on the whole observer on purpose: the latter would also move the flag writes out
 * of the transaction, so a failure there would surface as an error after the pallet
 * action had already committed — inviting a client retry that repeats it.
 */
class CellStatusLogObserver
{
    public function created(CellStatusLog $log): void
    {
        foreach ($this->matchingReasons($log) as $reason) {
            $log->flags()->create(['reason' => $reason]);
        }

        DB::afterCommit(fn () => DashboardStatsCache::flush());
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
