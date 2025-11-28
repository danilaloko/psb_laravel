<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\EmailController;
use App\Http\Controllers\AIAnalysisController;
use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;

// Главная страница - редирект на логин
Route::get('/', function () {
    return redirect('/login');
});

// Аутентификация
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login')->middleware('guest');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// Дашборд пользователя
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/task/{task}', [DashboardController::class, 'show'])->name('dashboard.task.show');
    Route::post('/dashboard/task/{task}/status', [DashboardController::class, 'updateStatus'])->name('dashboard.task.status');
    Route::post('/dashboard/task/{task}/analyze-latest-email', [TaskController::class, 'analyzeLatestEmail'])->name('dashboard.task.analyze');
    Route::get('/dashboard/task/{task}/analysis-status', [TaskController::class, 'getAnalysisStatus'])->name('dashboard.task.analysis-status');
    Route::get('/dashboard/email/{email}', [DashboardController::class, 'showEmail'])->name('dashboard.email.show');
    Route::post('/dashboard/email/{email}/analyze', [AIAnalysisController::class, 'processEmail'])->name('dashboard.email.analyze');
    Route::get('/dashboard/email/{email}/analysis-status', [AIAnalysisController::class, 'getAnalysisStatus'])->name('dashboard.email.analysis-status');

    // Задачи
    Route::get('/tasks/create', [TaskController::class, 'create'])->name('tasks.create');
    Route::post('/tasks', [TaskController::class, 'store'])->name('tasks.store');

    // Создание компании
    Route::get('/company/create', function () {
        return view('company.create');
    })->name('company.create');

    // Настройки пользователя
    Route::get('/settings', function () {
        return view('settings.index');
    })->name('settings');
});

// Админ панель
Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

    // Email management
    Route::post('/emails', [EmailController::class, 'store'])->name('emails.store');
    Route::post('/emails/process-incoming', [EmailController::class, 'processIncoming'])->name('emails.process-incoming');

    // AI Analysis
    Route::post('/emails/{email}/analyze', [AIAnalysisController::class, 'processEmail'])->name('emails.analyze');
    Route::get('/emails/{email}/analysis', [AIAnalysisController::class, 'showAnalysis'])->name('emails.analysis');
    Route::get('/emails/{email}/generations', [AIAnalysisController::class, 'getAllGenerations'])->name('emails.generations');
    Route::get('/ai/stats', [AIAnalysisController::class, 'getStats'])->name('ai.stats');
});
