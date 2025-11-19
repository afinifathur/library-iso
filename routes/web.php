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
| Public / Guest Routes
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login',     [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login',    [AuthController::class, 'login'])->name('login.attempt');

    Route::get('/register',  [AuthController::class, 'showRegisterForm'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.attempt');
});

// Logout (authenticated)
Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

// Redirect root to login (adjust if you prefer dashboard for authenticated users)
Route::get('/', fn() => redirect()->route('login'));

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');

    /*
    |--------------------------------------------------------------------------
    | Documents
    |--------------------------------------------------------------------------
    */
    Route::prefix('documents')->name('documents.')->group(function () {
        Route::get('/',                    [DocumentController::class, 'index'])->name('index');
        Route::get('/create',              [DocumentController::class, 'create'])->name('create');
        Route::post('/',                   [DocumentController::class, 'store'])->name('store');
        Route::post('/upload-pdf',         [DocumentController::class, 'uploadPdf'])->name('uploadPdf');

        Route::get('/{document}',          [DocumentController::class, 'show'])
            ->whereNumber('document')->name('show');

        Route::get('/{document}/compare',  [DocumentController::class, 'compare'])
            ->whereNumber('document')->name('compare');

        Route::get('/{document}/edit',     [DocumentController::class, 'edit'])
            ->whereNumber('document')->name('edit');

        Route::put('/{document}',          [DocumentController::class, 'updateCombined'])
            ->whereNumber('document')->name('updateCombined');

        // version download by id (named documents.versions.download)
        Route::get('/versions/{version}/download', [DocumentController::class, 'downloadVersion'])
            ->whereNumber('version')->name('versions.download');
    });

    /*
    |--------------------------------------------------------------------------
    | Categories
    |--------------------------------------------------------------------------
    */
    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');

    /*
    |--------------------------------------------------------------------------
    | Versions
    |--------------------------------------------------------------------------
    */
    Route::prefix('versions')->name('versions.')->group(function () {
        Route::get('/create',                     [DocumentVersionController::class, 'create'])->name('create');
        Route::post('/',                          [DocumentVersionController::class, 'store'])->name('store');

        Route::get('/{version}',                  [DocumentVersionController::class, 'show'])
            ->whereNumber('version')->name('show');

        Route::post('/{version}/mark-viewed',     [DocumentVersionController::class, 'markViewed'])
            ->whereNumber('version')->name('markViewed');

        Route::post('/{version}/submit',          [DocumentVersionController::class, 'submitForApproval'])
            ->whereNumber('version')->name('submit');

        Route::get('/{version}/edit',             [DocumentVersionController::class, 'edit'])
            ->whereNumber('version')->name('edit');

        Route::put('/{version}',                  [DocumentVersionController::class, 'update'])
            ->whereNumber('version')->name('update');

        // convenience aliases for choose-compare
        Route::get('/{version}/choose-compare',   [DocumentController::class, 'chooseCompare'])
            ->whereNumber('version')->name('chooseCompare');

        Route::get('/{version}/choose_compare',   [DocumentController::class, 'chooseCompare'])
            ->whereNumber('version')->name('choose_compare');
    });

    /*
    |--------------------------------------------------------------------------
    | Drafts
    |--------------------------------------------------------------------------
    */
    Route::prefix('drafts')->name('drafts.')->group(function () {
        Route::get('/',                          [DraftController::class, 'index'])->name('index');

        Route::get('/{version}',                 [DraftController::class, 'show'])
            ->whereNumber('version')->name('show');

        Route::get('/{version}/edit',            [DraftController::class, 'edit'])
            ->whereNumber('version')->name('edit');

        // For HTML forms that cannot send DELETE, provide POST alias
        Route::post('/{version}/delete',         [DraftController::class, 'destroy'])
            ->whereNumber('version')->name('destroy');

        Route::post('/{version}/submit',         [DraftController::class, 'submit'])
            ->whereNumber('version')->name('submit');

        Route::post('/{version}/reopen',         [DraftController::class, 'reopen'])
            ->whereNumber('version')->name('reopen');
    });

    /*
    |--------------------------------------------------------------------------
    | Approval routes (single index + explicit approve/reject)
    |--------------------------------------------------------------------------
    | Uses route-model-binding: `{version}` will resolve to DocumentVersion model
    */
    Route::get('/approval', [ApprovalController::class, 'index'])->name('approval.index');

    Route::post('/approval/{version}/approve', [ApprovalController::class, 'approve'])
        ->whereNumber('version')->name('approval.approve');

    Route::post('/approval/{version}/reject', [ApprovalController::class, 'reject'])
        ->whereNumber('version')->name('approval.reject');

    // Legacy alias (optional)
    Route::get('/approval-queue', [ApprovalController::class, 'index'])->name('approval.queue');
});

/*
|--------------------------------------------------------------------------
| Departments (public)
|--------------------------------------------------------------------------
*/
Route::get('/departments', [DepartmentController::class, 'index'])->name('departments.index');
Route::get('/departments/{department}', [DepartmentController::class, 'show'])
    ->whereNumber('department')->name('departments.show');

/*
|--------------------------------------------------------------------------
| Optional extra ISO routes (external file)
|--------------------------------------------------------------------------
*/
$isoRoutes = base_path('routes/iso_documents.php');
if (file_exists($isoRoutes)) {
    require $isoRoutes;
}
