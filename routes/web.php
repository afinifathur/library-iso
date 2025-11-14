<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\DocumentVersionController;
use App\Http\Controllers\DraftController;
use App\Http\Controllers\CategoryController;

/*
|--------------------------------------------------------------------------
| Web Routes (clean & consistent)
|--------------------------------------------------------------------------
|
| Aturan:
| - Gunakan route name konsisten (mis. documents.compare)
| - Kelompokkan route yang memerlukan auth
| - Batasi parameter numerik dengan whereNumber()
| - Sertakan file ISO opsional jika ada
|
*/

/*
|--------------------------------------------------------------------------
| AUTH (guest)
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login',     [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login',    [AuthController::class, 'login'])->name('login.attempt');

    Route::get('/register',  [AuthController::class, 'showRegisterForm'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.attempt');
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

/*
|--------------------------------------------------------------------------
| ROOT
|--------------------------------------------------------------------------
*/
Route::get('/', fn () => redirect()->route('login'));

/*
|--------------------------------------------------------------------------
| AUTHENTICATED ROUTES (group)
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');

    /*
    |--------------------------------------------------------------------------
    | DOCUMENTS (document-level routes)
    | - index/create/upload/show/compare/edit/updateCombined
    | - version download: documents.versions.download
    |--------------------------------------------------------------------------
    */
    Route::prefix('documents')->name('documents.')->group(function () {
        Route::get('', [DocumentController::class, 'index'])->name('index');
        Route::get('create', [DocumentController::class, 'create'])->name('create');
        Route::post('', [DocumentController::class, 'store'])->name('store');

        Route::post('upload-pdf', [DocumentController::class, 'uploadPdf'])->name('uploadPdf');

        // document-specific (use numeric constraint)
        Route::get('{document}', [DocumentController::class, 'show'])
            ->whereNumber('document')
            ->name('show');

        Route::get('{document}/compare', [DocumentController::class, 'compare'])
            ->whereNumber('document')
            ->name('compare'); // documents.compare

        Route::get('{document}/edit', [DocumentController::class, 'edit'])
            ->whereNumber('document')
            ->name('edit');

        Route::put('{document}', [DocumentController::class, 'updateCombined'])
            ->whereNumber('document')
            ->name('updateCombined');

        // download a specific version (kepraktisan: tetap di bawah prefix documents)
        Route::get('versions/{version}/download', [DocumentController::class, 'downloadVersion'])
            ->whereNumber('version')
            ->name('versions.download');
    });

    // Categories (simple)
    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');

    /*
    |--------------------------------------------------------------------------
    | VERSIONS (version-level routes)
    | - create/store/show/edit/update/submit
    |--------------------------------------------------------------------------
    */
    Route::prefix('versions')->name('versions.')->group(function () {
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
    | DRAFTS
    |--------------------------------------------------------------------------
    */
    Route::get('/drafts',                   [DraftController::class, 'index'])->name('drafts.index');
    Route::get('/drafts/{version}',         [DraftController::class, 'show'])->whereNumber('version')->name('drafts.show');
    Route::post('/drafts/{version}/delete', [DraftController::class, 'destroy'])->whereNumber('version')->name('drafts.destroy');
    Route::post('/drafts/{version}/reopen', [DraftController::class, 'reopen'])->whereNumber('version')->name('drafts.reopen');

    /*
    |--------------------------------------------------------------------------
    | APPROVAL QUEUE
    | - index, view (detail), approve, reject
    | - gunakan numeric constraint untuk {version}
    |--------------------------------------------------------------------------
    */
    Route::prefix('approval')->name('approval.')->group(function () {
        Route::get('', [ApprovalController::class, 'index'])->name('index');                 // approval.index
        Route::get('{version}/view', [ApprovalController::class, 'view'])                    // approval.view
            ->whereNumber('version')
            ->name('view');

        Route::post('{version}/approve', [ApprovalController::class, 'approve'])            // approval.approve
            ->whereNumber('version')
            ->name('approve');

        Route::post('{version}/reject', [ApprovalController::class, 'reject'])              // approval.reject
            ->whereNumber('version')
            ->name('reject');
    });
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
| OPTIONAL: EXTRA ISO ROUTES (file terpisah jika ada)
|--------------------------------------------------------------------------
*/
$isoRoutes = base_path('routes/iso_documents.php');
if (file_exists($isoRoutes)) {
    require $isoRoutes;
}

/*
|--------------------------------------------------------------------------
| NOTES
|--------------------------------------------------------------------------
| - Pastikan nama route pada view sesuai dengan nama di atas (mis. route('documents.compare'))
| - Jika views menggunakan route lama (mis. '/approval-queue'), pertahankan redirect di view atau tambahkan:
|     Route::get('/approval-queue', fn () => redirect()->route('approval.index'));
| - Jika ingin route model binding otomatis, sesuaikan type-hint pada controller (Document $document, etc.)
*/
