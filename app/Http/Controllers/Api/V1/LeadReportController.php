<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Reports\LeadReportService;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Printable PDF file of a lead (admin: any lead, dispatcher: own leads only).
 */
class LeadReportController extends Controller
{
    public function __invoke(Request $request, Lead $lead, LeadReportService $reports): Response
    {
        Gate::authorize('view', $lead);

        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return response($reports->render($lead, $request->user()), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$reports->filename($lead).'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
