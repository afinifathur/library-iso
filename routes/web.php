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
use App\Http\Controllers\RecycleController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Clean, ready-to-paste routes file for the Library-ISO application.
|
*/

/** Root redirect */
Route::redirect('/', '/login');

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

/*
|--------------------------------------------------------------------------
| Logout (requires auth)
|--------------------------------------------------------------------------
*/
Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

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
        Route::get('',                 [DocumentController::class, 'index'])->name('index');
        Route::get('create',           [DocumentController::class, 'create'])->name('create');
        Route::post('',                [DocumentController::class, 'store'])->name('store');
        Route::post('upload-pdf',      [DocumentController::class, 'uploadPdf'])->name('uploadPdf');

        // Show / edit / update / compare
        Route::get('{document}',       [DocumentController::class, 'show'])
            ->whereNumber('document')->name('show');

        Route::get('{document}/compare',[DocumentController::class, 'compare'])
            ->whereNumber('document')->name('compare');

        Route::get('{document}/edit',  [DocumentController::class, 'edit'])
            ->whereNumber('document')->name('edit');

        Route::put('{document}',       [DocumentController::class, 'updateCombined'])
            ->whereNumber('document')->name('updateCombined');

        /*
         | Version-specific actions (download, download-master, preview)
         | Named as documents.versions.* for consistency
         */
        Route::get('versions/{version}/download', [DocumentController::class, 'downloadVersion'])
            ->whereNumber('version')->name('versions.download');

        Route::get('versions/{version}/download-master', [DocumentController::class, 'downloadMaster'])
            ->whereNumber('version')->name('versions.downloadMaster');

        // Preview (only PDF inline) - kept inside auth
        Route::get('versions/{version}/preview', [DocumentController::class, 'previewVersion'])
            ->whereNumber('version')->name('versions.preview');
    });

    /*
    |--------------------------------------------------------------------------
    | Categories (read-only list)
    |--------------------------------------------------------------------------
    */
    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');

    /*
    |--------------------------------------------------------------------------
    | Versions (separate controller for version CRUD/workflow)
    |--------------------------------------------------------------------------
    */
    Route::prefix('versions')->name('versions.')->group(function () {
        Route::get('create',                   [DocumentVersionController::class, 'create'])->name('create');
        Route::post('',                        [DocumentVersionController::class, 'store'])->name('store');

        Route::get('{version}',                [DocumentVersionController::class, 'show'])
            ->whereNumber('version')->name('show');

        Route::post('{version}/submit',        [DocumentVersionController::class, 'submitForApproval'])
            ->whereNumber('version')->name('submit');

        Route::get('{version}/edit',           [DocumentVersionController::class, 'edit'])
            ->whereNumber('version')->name('edit');

        Route::put('{version}',                [DocumentVersionController::class, 'update'])
            ->whereNumber('version')->name('update');

        Route::get('{version}/choose-compare', [DocumentVersionController::class, 'chooseCompare'])
            ->whereNumber('version')->name('chooseCompare');

        // Mark version as trashed (controller handles authorization)
        Route::post('{version}/trash',         [DocumentController::class, 'trashVersion'])
            ->whereNumber('version')->name('trash');
    });

    /*
    |--------------------------------------------------------------------------
    | Drafts
    |--------------------------------------------------------------------------
    */
    Route::prefix('drafts')->name('drafts.')->group(function () {
        Route::get('/',                          [DraftController::class, 'index'])->name('index');

        Route::get('{version}',                  [DraftController::class, 'show'])
            ->whereNumber('version')->name('show');

        Route::get('{version}/edit',             [DraftController::class, 'edit'])
            ->whereNumber('version')->name('edit');

        // prefer DELETE, but provide POST alias for HTML forms
        Route::delete('{version}',               [DraftController::class, 'destroy'])
            ->whereNumber('version')->name('destroy');
        Route::post('{version}/delete',          [DraftController::class, 'destroy'])
            ->whereNumber('version')->name('destroy.post');

        Route::post('{version}/submit',          [DraftController::class, 'submit'])
            ->whereNumber('version')->name('submit');

        Route::post('{version}/reopen',          [DraftController::class, 'reopen'])
            ->whereNumber('version')->name('reopen');
    });

    /*
    |--------------------------------------------------------------------------
    | Approval Queue
    |--------------------------------------------------------------------------
    */
    Route::prefix('approval')->name('approval.')->group(function () {
        // Consider adding role-based middleware when needed:
        // ->middleware('role:mr|director|kabag|admin')
        Route::get('',                           [ApprovalController::class, 'index'])->name('index');

        Route::get('{version}/view',             [ApprovalController::class, 'view'])
            ->whereNumber('version')->name('view');

        Route::post('{version}/approve',         [ApprovalController::class, 'approve'])
            ->whereNumber('version')->name('approve');

        Route::post('{version}/reject',          [ApprovalController::class, 'reject'])
            ->whereNumber('version')->name('reject');
    });

    // Optional alias for approval queue
    Route::get('/approval-queue', [ApprovalController::class, 'index'])->name('approval.queue');

    /*
    |--------------------------------------------------------------------------
    | Recycle Bin (restore / permanent delete)
    |--------------------------------------------------------------------------
    */
    Route::prefix('recycle')->name('recycle.')->group(function () {
        Route::get('',                           [RecycleController::class, 'index'])->name('index');

        Route::post('{version}/restore',         [RecycleController::class, 'restore'])
            ->whereNumber('version')->name('restore');

        Route::delete('{version}',               [RecycleController::class, 'destroy'])
            ->whereNumber('version')->name('destroy');

        // HTML form compatibility
        Route::post('{version}/destroy',         [RecycleController::class, 'destroy'])
            ->whereNumber('version')->name('destroy.post');
    });
});

/*
|--------------------------------------------------------------------------
| Departments (public)
|--------------------------------------------------------------------------
|
| These routes are intentionally public so they can be referenced without auth.
| Move them inside the auth group if you want protection.
|
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
