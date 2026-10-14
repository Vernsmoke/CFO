<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\WorkflowController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');
Route::get('/login', [AuthController::class, 'show'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/{section}', [DashboardController::class, 'index'])->name('dashboard.section');
    Route::post('/profile', [WorkflowController::class, 'profile'])->name('profile.save');
    Route::patch('/profiles/{profile}', [WorkflowController::class, 'reviewProfile'])->name('profiles.review');
    Route::post('/activities', [WorkflowController::class, 'activity'])->name('activities.create');
    Route::post('/activities/{activity}/register', [WorkflowController::class, 'register'])->name('activities.register');
    Route::patch('/registrations/{registration}', [WorkflowController::class, 'reviewRegistration'])->name('registrations.review');
    Route::post('/registrations/{registration}/documents', [WorkflowController::class, 'upload'])->name('documents.upload');
    Route::patch('/documents/{document}', [WorkflowController::class, 'reviewDocument'])->name('documents.review');
    Route::get('/documents/{document}', [DocumentController::class, 'download'])->name('documents.download');
    Route::post('/registrations/{registration}/attendance', [WorkflowController::class, 'attendance'])->name('attendance.record');
    Route::post('/facilities', [WorkflowController::class, 'facility'])->name('facilities.create');
    Route::patch('/facilities/{facility}', [WorkflowController::class, 'decideFacility'])->name('facilities.decide');
    Route::post('/announcements', [WorkflowController::class, 'announcement'])->name('announcements.create');
    Route::post('/notifications/read', [WorkflowController::class, 'readNotifications'])->name('notifications.read');
    Route::patch('/accounts/{user}', [WorkflowController::class, 'account'])->name('accounts.update');
    Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');
});
