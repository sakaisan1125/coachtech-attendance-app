<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\TimecardController;
use App\Http\Controllers\AttendanceListController;
use App\Http\Controllers\AttendanceDetailController;
use App\Http\Controllers\UserRequestListController;
use App\Http\Controllers\Admin\AuthController;

Route::middleware(['auth'])->group(function () {
    Route::get('/attendance', [TimecardController::class, 'index'])->name('attendance');

    Route::post('/attendance/clock-in',  [TimecardController::class, 'clockIn'])->name('attendance.clock_in');
    Route::post('/attendance/break-start',[TimecardController::class, 'breakStart'])->name('attendance.break_start');
    Route::post('/attendance/break-end',  [TimecardController::class, 'breakEnd'])->name('attendance.break_end');
    Route::post('/attendance/clock-out',  [TimecardController::class, 'clockOut'])->name('attendance.clock_out');
    Route::get('/attendance/list', [AttendanceListController::class, 'index'])
        ->name('attendance.list');
    Route::get('/attendance/detail/{id}', [AttendanceDetailController::class, 'show'])
        ->whereNumber('id')
        ->name('attendance.detail');
    Route::post('/attendance/detail/{id}', [AttendanceDetailController::class, 'requestCorrection'])
        ->whereNumber('id')
        ->name('attendance.request');
    Route::get('/stamp_correction_request/list',[UserRequestListController::class,'pending'])
        ->name('requests.pending');
    Route::get('/stamp_correction_request/list/approved', [UserRequestListController::class, 'approved'])
        ->name('requests.approved');
});

Route::get('/logout',function(){
    Auth::logout();
    return redirect('/login');
});