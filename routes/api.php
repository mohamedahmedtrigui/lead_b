<?php

use App\Http\Controllers\Api\V1\Admin;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CallController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\LeadController;
use App\Http\Controllers\Api\V1\LeadReportController;
use App\Http\Controllers\Api\V1\NoteController;
use App\Http\Controllers\Api\V1\QualificationController;
use App\Http\Controllers\Api\V1\ScriptController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1
|--------------------------------------------------------------------------
| The React SPA authenticates with Sanctum session cookies:
|   1. GET /sanctum/csrf-cookie   2. POST /api/v1/auth/login
*/

Route::prefix('v1')->name('api.v1.')->group(function () {

    // --- Public authentication
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('register', [AuthController::class, 'register'])->middleware('throttle:register')->name('register');
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login')->name('login');
    });

    // --- Authenticated & approved users
    Route::middleware(['auth:sanctum', 'approved'])->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');

        Route::get('script', [ScriptController::class, 'index'])->name('script.index');

        // Leads (dispatchers only ever see their own, enforced server-side)
        Route::get('leads', [LeadController::class, 'index'])->name('leads.index');
        Route::get('leads/{lead}', [LeadController::class, 'show'])->name('leads.show');
        Route::get('leads/{lead}/report', LeadReportController::class)->middleware('throttle:reports')->name('leads.report');
        Route::get('leads/{lead}/timeline', [LeadController::class, 'timeline'])->name('leads.timeline');
        Route::get('leads/{lead}/notes', [NoteController::class, 'index'])->name('leads.notes.index');
        Route::post('leads/{lead}/notes', [NoteController::class, 'store'])->name('leads.notes.store');
        Route::get('leads/{lead}/qualification', [QualificationController::class, 'show'])->name('leads.qualification.show');

        // Dispatcher workspace
        Route::middleware('role:DISPATCHER')->group(function () {
            Route::get('dashboard', DashboardController::class)->name('dashboard');
            Route::post('leads/{lead}/calls', [CallController::class, 'start'])->name('leads.calls.start');
            Route::post('leads/{lead}/calls/outcome', [CallController::class, 'outcome'])->name('leads.calls.outcome');
            Route::patch('leads/{lead}/qualification', [QualificationController::class, 'update'])->name('leads.qualification.update');
            Route::post('leads/{lead}/qualification/complete', [QualificationController::class, 'complete'])->name('leads.qualification.complete');
        });

        // Administration
        Route::middleware('role:ADMIN')->prefix('admin')->name('admin.')->group(function () {
            Route::get('dashboard', Admin\DashboardController::class)->name('dashboard');

            Route::get('dispatchers', [Admin\DispatcherController::class, 'index'])->name('dispatchers.index');
            Route::post('dispatchers', [Admin\DispatcherController::class, 'store'])->name('dispatchers.store');
            Route::patch('dispatchers/{user}', [Admin\DispatcherController::class, 'update'])->name('dispatchers.update');
            Route::post('dispatchers/{user}/approve', [Admin\DispatcherController::class, 'approve'])->name('dispatchers.approve');
            Route::post('dispatchers/{user}/reject', [Admin\DispatcherController::class, 'reject'])->name('dispatchers.reject');
            Route::post('dispatchers/{user}/deactivate', [Admin\DispatcherController::class, 'deactivate'])->name('dispatchers.deactivate');
            Route::post('dispatchers/{user}/reactivate', [Admin\DispatcherController::class, 'reactivate'])->name('dispatchers.reactivate');
            Route::post('dispatchers/{user}/allocate', [Admin\DispatcherController::class, 'allocate'])->name('dispatchers.allocate');

            Route::post('leads/assign', [Admin\LeadManagementController::class, 'assign'])->name('leads.assign');
            Route::post('leads/distribute', [Admin\LeadManagementController::class, 'distribute'])->name('leads.distribute');
            Route::get('leads/allocatable', [Admin\LeadManagementController::class, 'allocatable'])->name('leads.allocatable');
            Route::get('leads/export', Admin\LeadExportController::class)->name('leads.export');
            Route::get('leads/imports', [Admin\LeadImportController::class, 'index'])->name('leads.imports.index');
            Route::post('leads/imports', [Admin\LeadImportController::class, 'store'])->middleware('throttle:import')->name('leads.imports.store');
            Route::patch('leads/{lead}/status', [Admin\LeadManagementController::class, 'updateStatus'])->name('leads.status');

            Route::get('script-steps', [Admin\ScriptStepController::class, 'index'])->name('script-steps.index');
            Route::put('script-steps/{scriptStep}', [Admin\ScriptStepController::class, 'update'])->name('script-steps.update');
            Route::post('script-steps/{scriptStep}/reset', [Admin\ScriptStepController::class, 'reset'])->name('script-steps.reset');

            Route::get('calls', [Admin\ActivityController::class, 'calls'])->name('calls.index');
            Route::get('audit-logs', [Admin\ActivityController::class, 'audit'])->name('audit-logs.index');
        });
    });
});
