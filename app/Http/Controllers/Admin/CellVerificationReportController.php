<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\CellVerificationReportNotAcceptableException;
use App\Exceptions\InvalidSlotStateException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AcceptCellVerificationReportRequest;
use App\Models\CellVerificationReport;
use App\Models\User;
use App\Services\CellVerificationService;
use Illuminate\Http\RedirectResponse;

class CellVerificationReportController extends Controller
{
    /**
     * Accept an incorrect report, updating its cell to match what was reported
     * (see CellVerificationService::acceptReport()). A refusal — the cell
     * changed since, its row is being verified again, the report was already
     * accepted — is surfaced as a non-field `action` error for the page's
     * ActionErrorBanner, since that state can change after the page rendered.
     */
    public function accept(AcceptCellVerificationReportRequest $request, CellVerificationReport $cellVerificationReport, CellVerificationService $cellVerifications): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $redirect = $this->redirectAfterAccept($request, $cellVerificationReport);

        try {
            $cellVerifications->acceptReport($cellVerificationReport, $user->id);
        } catch (CellVerificationReportNotAcceptableException|InvalidSlotStateException $e) {
            return $redirect->withErrors(['action' => $e->getMessage()]);
        }

        return $redirect;
    }

    /**
     * Triggered from both CellVerificationRounds/Show.vue (the default) and
     * Users/Show.vue ('return_to' => 'user'), which the Referer header/session
     * can't reliably tell apart — same as CellStatusLogController::
     * redirectAfterAcknowledge(). Each target is the report's own round or
     * reporter, never a request-supplied id.
     */
    private function redirectAfterAccept(AcceptCellVerificationReportRequest $request, CellVerificationReport $report): RedirectResponse
    {
        if ($request->input('return_to') === 'user') {
            return $this->redirectPreservingQuery('admin.users.show', $request, ['user' => $report->user_id]);
        }

        return $this->redirectPreservingQuery('admin.cell-verification-rounds.show', $request, [
            'cellVerificationRound' => $report->cell_verification_round_id,
        ]);
    }
}
