<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\TimecardController;
use App\Http\Controllers\AttendanceListController;
use App\Http\Controllers\AttendanceDetailController;
use App\Http\Controllers\UserRequestListController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\AdminController;

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
    Route::get('/stamp_correction_request/list', function () {
    $user = auth()->user();

    if ($user && $user->role === 'admin') {
        return app(\App\Http\Controllers\AdminController::class)->adminRequestPending(request());
    }

    // デフォルトは一般ユーザー用
        return app(\App\Http\Controllers\UserRequestListController::class)->pending(request());
    })->name('requests.pending');

    Route::get('/stamp_correction_request/list/approved', function () {
        $user = auth()->user();

        if ($user && $user->role === 'admin') {
            return app(\App\Http\Controllers\AdminController::class)->adminRequestApproved(request());
        }

        return app(\App\Http\Controllers\UserRequestListController::class)->approved(request());
    })->name('requests.approved');
    Route::get('admin/attendance/list', [AdminController::class, 'adminAttendanceList'])
        ->name('admin.attendance.list');
    
    Route::get('admin/attendance/{id}', [AdminController::class, 'adminAttendanceShow'])->whereNumber('id')->name('admin.detail');
    Route::post('admin/attendance/{id}', [AdminController::class, 'adminAttendanceUpdate'])
    ->whereNumber('id')
    ->name('admin.update');
});

Route::get('/logout',function(){
    Auth::logout();
    return redirect('/login');
});

Route::get('/admin/login', function () {
    return view('auth.admin-login');
})->name('admin.login');

Route::post('/admin/login', [AuthenticatedSessionController::class, 'store']);
Route::post('/login', [AuthenticatedSessionController::class, 'store']);

