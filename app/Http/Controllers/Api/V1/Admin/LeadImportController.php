<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Leads\Enums\LeadStatus;
use App\Domain\Leads\Services\LeadAssignmentService;
use App\Domain\Leads\Services\LeadImportService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportLeadsRequest;
use App\Models\Lead;
use App\Models\LeadImport;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class LeadImportController extends Controller
{
    public function index(): array
    {
        return [
            'data' => LeadImport::query()->with('user')->latest()->limit(20)->get()->map(fn (LeadImport $i) => [
                'id' => $i->id,
                'file_name' => $i->file_name,
                'total_rows' => $i->total_rows,
                'created_count' => $i->created_count,
                'updated_count' => $i->updated_count,
                'skipped_count' => $i->skipped_count,
                'errors' => $i->errors ?? [],
                'user' => $i->user?->full_name,
                'created_at' => $i->created_at?->toIso8601String(),
            ]),
        ];
    }

    public function store(ImportLeadsRequest $request, LeadImportService $importer, LeadAssignmentService $assignments): JsonResponse
    {
        $file = $request->file('file');
        $import = $importer->import($file->getRealPath(), $file->getClientOriginalName(), $request->user());

        $distribution = null;
        if ($request->boolean('distribute')) {
            $leads = Lead::query()
                ->whereNull('assigned_to')
                ->whereIn('status', LeadStatus::open())
                ->orderBy('source_created_at')
                ->orderBy('id')
                ->get();
            $dispatchers = User::query()->activeDispatchers()->get();

            if ($leads->isNotEmpty() && $dispatchers->isNotEmpty()) {
                $distribution = $assignments->distribute(
                    $leads,
                    $dispatchers,
                    $request->user(),
                    $request->input('strategy') ?? LeadAssignmentService::STRATEGY_ROUND_ROBIN,
                );
            }
        }

        return response()->json([
            'import' => [
                'id' => $import->id,
                'file_name' => $import->file_name,
                'total_rows' => $import->total_rows,
                'created_count' => $import->created_count,
                'updated_count' => $import->updated_count,
                'skipped_count' => $import->skipped_count,
                'errors' => $import->errors ?? [],
            ],
            'distribution' => $distribution,
        ], 201);
    }
}
