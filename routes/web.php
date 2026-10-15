<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\StaffAttendanceController;
use App\Http\Controllers\StudentAccountController;
use App\Http\Controllers\WorkflowController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');
Route::get('/login', [AuthController::class, 'show'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::get('/register/student', [StudentAccountController::class, 'create'])->name('student.register');
Route::post('/register/student', [StudentAccountController::class, 'store'])->middleware('throttle:5,1')->name('student.register.store');
Route::get('/student-email/pending', [StudentAccountController::class, 'pending'])->name('student.verification.pending');
Route::post('/student-email/resend', [StudentAccountController::class, 'resend'])->name('student.verification.resend');
Route::get('/student-email/verify/{token}', [StudentAccountController::class, 'verify'])->middleware('throttle:10,1')->name('student.verification.verify');
Route::get('/forgot-password', [StudentAccountController::class, 'forgotPassword'])->name('student.password.forgot');
Route::post('/forgot-password', [StudentAccountController::class, 'sendPasswordReset'])->middleware('throttle:5,1')->name('student.password.email');
Route::get('/reset-password/{token}', [StudentAccountController::class, 'resetPasswordForm'])->name('student.password.reset.form');
Route::post('/reset-password', [StudentAccountController::class, 'resetPassword'])->middleware('throttle:5,1')->name('student.password.reset');
Route::view('/terms', 'legal.terms')->name('terms');
Route::view('/privacy', 'legal.privacy')->name('privacy');

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
    Route::post('/registrations/{registration}/requirements/{requirement}', [WorkflowController::class, 'submitRequirement'])->name('requirements.submit');
    Route::patch('/documents/{document}', [WorkflowController::class, 'reviewDocument'])->name('documents.review');
    Route::patch('/requirement-submissions/{submission}', [WorkflowController::class, 'reviewRequirementSubmission'])->name('requirements.review');
    Route::get('/documents/{document}', [DocumentController::class, 'download'])->name('documents.download');
    Route::get('/requirement-submissions/{submission}', [DocumentController::class, 'downloadRequirement'])->name('requirements.download');
    Route::get('/registrations/{registration}/form', [DocumentController::class, 'registrationForm'])->name('registrations.form');
    Route::post('/staff-attendance', [StaffAttendanceController::class, 'record'])->name('staff-attendance.record');
    Route::get('/staff-attendance/{attendance}/photo/{checkpoint}', [StaffAttendanceController::class, 'photo'])->name('staff-attendance.photo');
    Route::patch('/staff-attendance/{attendance}', [StaffAttendanceController::class, 'update'])->name('staff-attendance.update');
    Route::post('/facilities', [WorkflowController::class, 'facility'])->name('facilities.create');
    Route::patch('/facilities/{facility}', [WorkflowController::class, 'decideFacility'])->name('facilities.decide');
    Route::post('/announcements', [WorkflowController::class, 'announcement'])->name('announcements.create');
    Route::post('/notifications/read', [WorkflowController::class, 'readNotifications'])->name('notifications.read');
    Route::patch('/accounts/{user}', [WorkflowController::class, 'account'])->name('accounts.update');
    Route::post('/accounts', [WorkflowController::class, 'createAccount'])->name('accounts.create');
    Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');
});
