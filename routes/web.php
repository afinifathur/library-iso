<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\DocumentVersionController;

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
*/
Route::redirect('/', '/login');

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
| Termasuk: create/upload/show/compare/edit/updateCombined + download version
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->prefix('documents')->name('documents.')->group(function () {
    // create + upload
    Route::get('create', [DocumentController::class, 'create'])->name('create');
    Route::post('upload-pdf', [DocumentController::class, 'uploadPdf'])->name('uploadPdf');

    // detail dokumen
    Route::get('{document}', [DocumentController::class, 'show'])
        ->whereNumber('document')
        ->name('show');

    // compare versi dokumen
    Route::get('{document}/compare', [DocumentController::class, 'compare'])
        ->whereNumber('document')
        ->name('compare');

    // form edit metadata (opsional, tetap dipakai view lama)
    Route::get('{document}/edit', [DocumentController::class, 'edit'])
        ->whereNumber('document')
        ->name('edit');

    // UPDATE COMBINED (menggantikan update lama)
    Route::put('{document}', [DocumentController::class, 'updateCombined'])
        ->whereNumber('document')
        ->name('updateCombined');

    // download berkas versi (via DocumentController)
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
    // create/store
    Route::get('create', [DocumentVersionController::class, 'create'])->name('create');
    Route::post('',       [DocumentVersionController::class, 'store'])->name('store');

    // show single version (baru, sesuai instruksi)
    Route::get('{version}', [DocumentVersionController::class, 'show'])
        ->whereNumber('version')
        ->name('show');

    // submit for approval
    Route::post('{version}/submit', [DocumentVersionController::class, 'submitForApproval'])
        ->whereNumber('version')
        ->name('submit');

    // edit/update
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
| APPROVAL QUEUE (auth)
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::get('/approval', [ApprovalController::class, 'index'])->name('approval.index');

    // pertahankan URL lama sebagai redirect ke /approval
    Route::redirect('/approval-queue', '/approval');

    Route::post('/approval/{version}/approve', [ApprovalController::class, 'approve'])
        ->whereNumber('version')
        ->name('approval.approve');

    Route::post('/approval/{version}/reject',  [ApprovalController::class, 'reject'])
        ->whereNumber('version')
        ->name('approval.reject');
});
