<?php

use App\Http\Controllers\Auth\GoogleVerificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\AutoRecordsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ManualCountController;
use App\Http\Controllers\PredictionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\SessionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Guest Routes (no authentication required)
|--------------------------------------------------------------------------
*/

// Landing / redirect to login
Route::get('/', function () {
    return redirect('/login');
});

// Login routes
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:6,1');

// Registration routes
Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('register');
Route::post('/register', [RegisterController::class, 'register'])->middleware('throttle:5,1');

Route::get('/auth/google/redirect', [SocialAuthController::class, 'redirect'])
    ->middleware('throttle:20,1')
    ->name('oauth.redirect');
Route::get('/auth/google/callback', [SocialAuthController::class, 'callback'])
    ->middleware('throttle:20,1')
    ->name('oauth.callback');
Route::get('/auth/google/verify', [GoogleVerificationController::class, 'show'])
    ->name('google.verify.notice');
Route::post('/auth/google/verify', [GoogleVerificationController::class, 'verify'])
    ->middleware('throttle:6,1')
    ->name('google.verify');
Route::post('/auth/google/verify/resend', [GoogleVerificationController::class, 'resend'])
    ->middleware('throttle:3,10')
    ->name('google.verify.resend');

// Password reset (placeholder – will implement later)
Route::get('/forgot-password', function () {
    return view('auth.login');
})->name('password.request');

/*
|--------------------------------------------------------------------------
| Protected Routes (require authentication)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])->group(function () {

    // Logout (POST request for security)
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    // Main application pages
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // AJAX endpoint for dashboard data (NO FLASH - loads data dynamically)
    Route::get('/dashboard-data', [DashboardController::class, 'getDashboardData'])->name('dashboard.data');

    Route::get('/live-traffic', function () {
        return view('live-traffic');
    })->name('live-traffic');

    // MANUAL COUNT FORM (existing - with +/- buttons)
    Route::get('/manual-count', function () {
        return view('manual-count');
    })->name('manual-count');

    // MANUAL RECORDS (formerly sessions - shows manually entered data)
    Route::get('/manual-records', [SessionController::class, 'manualRecords'])->name('manual-records');

    // AUTO RECORDS (automatic counting from cameras)
    Route::get('/auto-records', [AutoRecordsController::class, 'index'])->name('auto-records');
    Route::put('/auto-sessions/{autoSession}', [AutoRecordsController::class, 'update'])->name('auto-sessions.update');
    Route::delete('/auto-sessions/{autoSession}', [AutoRecordsController::class, 'destroy'])->name('auto-sessions.destroy');

    // DELETE ALL auto sessions (MUST come BEFORE the {autoSession} route)
    Route::delete('/auto-sessions/delete-all', [AutoRecordsController::class, 'destroyAll'])->name('auto-sessions.delete-all');

    // SCHEDULING PANEL
    Route::get('/schedules', [ScheduleController::class, 'index'])->name('schedules');
    Route::post('/schedules', [ScheduleController::class, 'store'])->name('schedules.store');
    Route::put('/schedules/{schedule}', [ScheduleController::class, 'update'])->name('schedules.update');
    Route::delete('/schedules/{schedule}', [ScheduleController::class, 'destroy'])->name('schedules.destroy');
    Route::patch('/schedules/{schedule}/toggle', [ScheduleController::class, 'toggle'])->name('schedules.toggle');
    Route::get('/schedules/{schedule}/logs', [ScheduleController::class, 'logs'])->name('schedules.logs');

    // PREDICTIONS PANEL
    Route::get('/predictions', [PredictionController::class, 'index'])->name('predictions');
    Route::post('/predictions/get', [PredictionController::class, 'getPrediction'])->name('predictions.get');
    Route::post('/predictions/train', [PredictionController::class, 'train'])->name('predictions.train');

    // REPORTS PANEL - Connected to database
    Route::get('/reports', [ReportController::class, 'index'])->name('reports');
    Route::post('/reports/export-csv', [ReportController::class, 'exportCsv'])->name('reports.export-csv');
    Route::post('/reports/generate-pdf', [ReportController::class, 'generatePdf'])->name('reports.generate-pdf');

    // Cameras, Admin pages
    Route::get('/cameras', function () {
        return view('cameras');
    })->name('cameras');

    Route::get('/admin', function () {
        return view('admin');
    })->name('admin');

    // Store manual count (AJAX)
    Route::post('/manual-count/store', [ManualCountController::class, 'store'])->name('manual-count.store');

    // EDIT & DELETE routes for sessions (manual records)
    // IMPORTANT: delete-all route must come BEFORE {session} route to avoid conflict
    Route::delete('/sessions/delete-all', [SessionController::class, 'destroyAll'])->name('sessions.delete-all');
    Route::put('/sessions/{session}', [SessionController::class, 'update'])->name('sessions.update');
    Route::delete('/sessions/{session}', [SessionController::class, 'destroy'])->name('sessions.destroy');

    // FULL UPDATE route for editing vehicle counts (must come BEFORE {session} route)
    Route::put('/sessions/{session}/full-update', [SessionController::class, 'fullUpdate'])->name('sessions.full-update');
});

/*
|--------------------------------------------------------------------------
| Fallback (optional – 404 page)
|--------------------------------------------------------------------------
*/
// Route::fallback(function () {
//     return view('errors.404');
// });
