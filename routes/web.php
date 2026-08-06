<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->name('register.store');
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::post('/attendance', [AttendanceController::class, 'store'])->name('attendance.store');
    Route::get('/attendance/download', [AttendanceController::class, 'download'])->name('attendance.download');
    Route::post('/attendance/upload', [AttendanceController::class, 'upload'])->name('attendance.upload');
    Route::post('/attendance/entry', [AttendanceController::class, 'storeEntry'])->name('attendance.entry.store');
    Route::delete('/attendance/entry/{entry}', [AttendanceController::class, 'destroyEntry'])->name('attendance.entry.destroy');
});
