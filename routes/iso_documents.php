<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DocumentVersionController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\ApprovalQueueController;
use App\Http\Controllers\RevisionHistoryController;
use App\Http\Controllers\AuditLogController;

// ── PHASE C2B: FPDI POC — TEMPORARY (remove after C2B is complete) ──────────
use App\Http\Controllers\PdfStampPocController;
// ────────────────────────────────────────────────────────────────────────────

/*
|--------------------------------------------------------------------------
| ISO Documents Routes (AUTH MODE)
|--------------------------------------------------------------------------
| Semua route dilindungi middleware 'auth'.
| Catatan: Route /dashboard DIKELOLA di routes/web.php (jangan duplikat di sini).
*/

Route::middleware('auth')->group(function () {

    // Documents
    Route::prefix('documents')->name('documents.')->group(function () {
        Route::get('/',          [DocumentController::class, 'index'])->name('index');
        Route::get('/create',    [DocumentController::class, 'create'])->name('create');

        // Upload dibatasi role MR/Admin/Kabag
        Route::post('/upload-pdf', [DocumentController::class, 'uploadPdf'])
            ->middleware('role:mr|admin|kabag')
            ->name('uploadPdf');

        Route::get('/{document}', [DocumentController::class, 'show'])->name('show');
    });

    // Versions (download/approval)
    Route::prefix('versions')->name('versions.')->group(function () {
        Route::get('/{version}/download', [DocumentVersionController::class, 'download'])->name('download');
        Route::post('/{version}/approve',  [DocumentVersionController::class, 'approve'])->name('approve');
        Route::post('/{version}/reject',   [DocumentVersionController::class, 'reject'])->name('reject');
    });

    // Categories (read-only)
    Route::prefix('categories')->name('categories.')->group(function () {
        Route::get('/',            [CategoryController::class, 'index'])->name('index');
        Route::get('/{category}',  [CategoryController::class, 'show'])->name('show');
    });

    // Departments (read-only)
    Route::prefix('departments')->name('departments.')->group(function () {
        Route::get('/',               [DepartmentController::class, 'index'])->name('index');
        Route::get('/{department}',   [DepartmentController::class, 'show'])->name('show');
    });

    // Queues, History, Audit
    Route::get('/approval-queue',   [ApprovalQueueController::class, 'index'])->name('approval.queue-legacy');
    Route::get('/revision-history', [RevisionHistoryController::class, 'index'])->name('revision.index');
    Route::get('/audit-log',        [AuditLogController::class, 'index'])->name('audit.index');
    Route::get('/distribution-register', [App\Http\Controllers\DistributionRegisterController::class, 'index'])->name('distribution.index');

    // ── PHASE C2B: FPDI POC routes — TEMPORARY (remove after C2B) ───────────
    Route::get('/pdf-stamp-poc',       [PdfStampPocController::class, 'index'])->name('poc.stamp.index');
    Route::get('/pdf-stamp-poc/{type}', [PdfStampPocController::class, 'stamp'])->name('poc.stamp');
    // ────────────────────────────────────────────────────────────────────────
});
