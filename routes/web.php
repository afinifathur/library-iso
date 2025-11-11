<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\DocumentVersionController;
use App\Http\Controllers\DraftController;

/*
|--------------------------------------------------------------------------
| AUTH
|--------------------------------------------------------------------------
*/
Route::get('/login',     [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login',    [AuthController::class, 'login'])->name('login.attempt');

Route::get('/register',  [AuthController::class, 'showRegisterForm'])->name('register');
Route::post('/register', [AuthController::class, 'register'])->name('register.attempt');

Route::post('/logout',   [AuthController::class, 'logout'])->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| ROOT
|--------------------------------------------------------------------------
| Gunakan redirect ke nama route agar aman untuk instalasi subfolder.
*/
Route::get('/', fn () => redirect()->route('login'));

/*
|--------------------------------------------------------------------------
| DASHBOARD (auth)
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');

/*
|--------------------------------------------------------------------------
| EXTRA ISO ROUTES (opsional file terpisah)
|--------------------------------------------------------------------------
*/
$isoRoutes = base_path('routes/iso_documents.php');
if (file_exists($isoRoutes)) {
    require $isoRoutes;
}

/*
|--------------------------------------------------------------------------
| DOCUMENTS (auth)
| Termasuk: index/create/upload/show/compare/edit/updateCombined + download version
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->prefix('documents')->name('documents.')->group(function () {
    Route::get('', [DocumentController::class, 'index'])->name('index');
    Route::post('', [DocumentController::class, 'store'])->name('store');

    Route::get('create', [DocumentController::class, 'create'])->name('create');
    Route::post('upload-pdf', [DocumentController::class, 'uploadPdf'])->name('uploadPdf');

    Route::get('{document}', [DocumentController::class, 'show'])
        ->whereNumber('document')
        ->name('show');

    Route::get('{document}/compare', [DocumentController::class, 'compare'])
        ->whereNumber('document')
        ->name('compare');

    Route::get('{document}/edit', [DocumentController::class, 'edit'])
        ->whereNumber('document')
        ->name('edit');

    Route::put('{document}', [DocumentController::class, 'updateCombined'])
        ->whereNumber('document')
        ->name('updateCombined');

    Route::get('versions/{version}/download', [DocumentController::class, 'downloadVersion'])
        ->whereNumber('version')
        ->name('versions.download');
});

/*
|--------------------------------------------------------------------------
| VERSIONS (auth)
| Termasuk: create/store/show/edit/update/submit
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->prefix('versions')->name('versions.')->group(function () {
    Route::get('create', [DocumentVersionController::class, 'create'])->name('create');
    Route::post('', [DocumentVersionController::class, 'store'])->name('store');

    Route::get('{version}', [DocumentVersionController::class, 'show'])
        ->whereNumber('version')
        ->name('show');

    Route::post('{version}/submit', [DocumentVersionController::class, 'submitForApproval'])
        ->whereNumber('version')
        ->name('submit');

    Route::get('{version}/edit', [DocumentVersionController::class, 'edit'])
        ->whereNumber('version')
        ->name('edit');

    Route::put('{version}', [DocumentVersionController::class, 'update'])
        ->whereNumber('version')
        ->name('update');
});

/*
|--------------------------------------------------------------------------
| DEPARTMENTS (public)
|--------------------------------------------------------------------------
*/
Route::get('/departments',              [DepartmentController::class, 'index'])->name('departments.index');
Route::get('/departments/{department}', [DepartmentController::class, 'show'])->name('departments.show');

/*
|--------------------------------------------------------------------------
| DRAFTS (auth)
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::get('/drafts',                   [DraftController::class, 'index'])->name('drafts.index'); // Draft Container
    Route::get('/drafts/{version}',         [DraftController::class, 'show'])->name('drafts.show');
    Route::post('/drafts/{version}/delete', [DraftController::class, 'destroy'])->name('drafts.destroy');
    Route::post('/drafts/{version}/reopen', [DraftController::class, 'reopen'])->name('drafts.reopen');
});

/*
|--------------------------------------------------------------------------
| APPROVAL QUEUE (auth)
| NOTE: gunakan redirect()->route(...) agar URL mengikuti subfolder/index.php
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::get('/approval', [ApprovalController::class, 'index'])->name('approval.index');

    // URL lama -> baru
    Route::get('/approval-queue', fn () => redirect()->route('approval.index'));

    Route::post('/approval/{version}/approve', [ApprovalController::class, 'approve'])
        ->whereNumber('version')
        ->name('approval.approve');

    // Opsi A: sesuaikan placeholder dengan parameter controller ($versionId)
    Route::post('/approval/{versionId}/reject', [ApprovalController::class, 'reject'])
        ->whereNumber('versionId')
        ->name('approval.reject');
});
