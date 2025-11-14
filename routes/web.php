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
Route::get('/', fn() => redirect()->route('login'));


/*
|--------------------------------------------------------------------------
| AUTHENTICATED ROUTES
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard.index');


    /*
    |--------------------------------------------------------------------------
    | DOCUMENTS
    |--------------------------------------------------------------------------
    */
    Route::prefix('documents')->name('documents.')->group(function () {

        // List documents
        Route::get('', [DocumentController::class, 'index'])->name('index');

        // Create form
        Route::get('create', [DocumentController::class, 'create'])->name('create');

        // ➤ IMPORTANT: POST /documents (baseline create)
        Route::post('', [DocumentController::class, 'store'])->name('store');

        // Upload (draft / version upload)
        Route::post('upload-pdf', [DocumentController::class, 'uploadPdf'])->name('uploadPdf');

        // Show document
        Route::get('{document}', [DocumentController::class, 'show'])
            ->whereNumber('document')
            ->name('show');

        // Compare versions
        Route::get('{document}/compare', [DocumentController::class, 'compare'])
            ->whereNumber('document')
            ->name('compare');

        // Edit document
        Route::get('{document}/edit', [DocumentController::class, 'edit'])
            ->whereNumber('document')
            ->name('edit');

        // Update metadata + version (combined)
        Route::put('{document}', [DocumentController::class, 'updateCombined'])
            ->whereNumber('document')
            ->name('updateCombined');

        // Download version file
        Route::get('versions/{version}/download', [DocumentController::class, 'downloadVersion'])
            ->whereNumber('version')
            ->name('versions.download');
    });


    /*
    |--------------------------------------------------------------------------
    | CATEGORIES
    |--------------------------------------------------------------------------
    */
    Route::get('/categories', [CategoryController::class, 'index'])
        ->name('categories.index');


    /*
    |--------------------------------------------------------------------------
    | VERSIONS
    |--------------------------------------------------------------------------
    */
    Route::prefix('versions')->name('versions.')->group(function () {

        Route::get('create', [DocumentVersionController::class, 'create'])->name('create');
        Route::post('',      [DocumentVersionController::class, 'store'])->name('store');

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
    Route::prefix('drafts')->name('drafts.')->group(function () {
        Route::get('',                [DraftController::class, 'index'])->name('index');
        Route::get('{version}',       [DraftController::class, 'show'])->whereNumber('version')->name('show');
        Route::post('{version}/delete',[DraftController::class, 'destroy'])->whereNumber('version')->name('destroy');
        Route::post('{version}/reopen',[DraftController::class, 'reopen'])->whereNumber('version')->name('reopen');
    });


    /*
    |--------------------------------------------------------------------------
    | APPROVAL QUEUE
    |--------------------------------------------------------------------------
    */
    Route::prefix('approval')->name('approval.')->group(function () {

        Route::get('', [ApprovalController::class, 'index'])->name('index');

        Route::get('{version}/view',  [ApprovalController::class, 'view'])
            ->whereNumber('version')
            ->name('view');

        Route::post('{version}/approve', [ApprovalController::class, 'approve'])
            ->whereNumber('version')
            ->name('approve');

        Route::post('{version}/reject',  [ApprovalController::class, 'reject'])
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
| OPTIONAL EXTRA ISO ROUTES
|--------------------------------------------------------------------------
*/
$isoRoutes = base_path('routes/iso_documents.php');
if (file_exists($isoRoutes)) {
    require $isoRoutes;
}
