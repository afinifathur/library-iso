<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;

/*
|--------------------------------------------------------------------------
| AUTH ROUTES (Manual, tanpa Breeze)
|--------------------------------------------------------------------------
*/
Route::get('/login',  [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');

Route::get('/register',  [AuthController::class, 'showRegisterForm'])->name('register');
Route::post('/register', [AuthController::class, 'register'])->name('register.attempt');

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

/*
|--------------------------------------------------------------------------
| ROOT REDIRECT
|--------------------------------------------------------------------------
*/
Route::get('/', fn () => redirect()->route('login'));

/*
|--------------------------------------------------------------------------
| DASHBOARD
|--------------------------------------------------------------------------
*/
Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware('auth')
    ->name('dashboard.index');

/*
|--------------------------------------------------------------------------
| ISO ROUTES (file terpisah)
|--------------------------------------------------------------------------
*/
$isoRoutes = base_path('routes/iso_documents.php');
if (file_exists($isoRoutes)) {
    require $isoRoutes;
}

/*
|--------------------------------------------------------------------------
| DOCUMENT ROUTES (Pastikan tersedia)
|--------------------------------------------------------------------------
*/
Route::get('/documents/create', [DocumentController::class, 'create'])
    ->middleware('auth')
    ->name('documents.create');

/*
|--------------------------------------------------------------------------
| UPLOAD PDF ROUTE (IMPORTANT: tanpa middleware role!)
|--------------------------------------------------------------------------
*/
Route::post('/documents/upload-pdf', [DocumentController::class, 'uploadPdf'])
    ->middleware('auth')   // ✅ ganti dari role:mr|admin|kabag menjadi auth saja
    ->name('documents.uploadPdf');

/*
|--------------------------------------------------------------------------
| DOCUMENT COMPARE ROUTE
|--------------------------------------------------------------------------
*/
Route::get('/documents/{document}/compare', [DocumentController::class, 'compare'])
    ->middleware('auth')
    ->name('documents.compare');
