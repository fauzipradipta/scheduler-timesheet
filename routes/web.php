<?php

use App\Http\Controllers\AttendanceController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');
Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
Route::post('/attendance', [AttendanceController::class, 'store'])->name('attendance.store');
Route::get('/attendance/download', [AttendanceController::class, 'download'])->name('attendance.download');
Route::post('/attendance/upload', [AttendanceController::class, 'upload'])->name('attendance.upload');
Route::post('/attendance/entry', [AttendanceController::class, 'storeEntry'])->name('attendance.entry.store');
Route::delete('/attendance/entry/{entry}', [AttendanceController::class, 'destroyEntry'])->name('attendance.entry.destroy');
