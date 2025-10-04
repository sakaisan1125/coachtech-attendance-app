<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\TimecardController;
use App\Http\Controllers\AttendanceListController;
use App\Http\Controllers\AttendanceDetailController;
use App\Http\Controllers\UserRequestListController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\Auth\RegisterController;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/attendance', [TimecardController::class, 'index'])->name('attendance');
    Route::post('/attendance/clock-in', [TimecardController::class, 'clockIn'])->name('attendance.clock_in');
    Route::post('/attendance/break-start', [TimecardController::class, 'breakStart'])->name('attendance.break_start');
    Route::post('/attendance/break-end', [TimecardController::class, 'breakEnd'])->name('attendance.break_end');
    Route::post('/attendance/clock-out', [TimecardController::class, 'clockOut'])->name('attendance.clock_out');
    Route::get('/attendance/list', [AttendanceListController::class, 'index'])->name('attendance.list');
    Route::get('/attendance/detail/{id}', [AttendanceDetailController::class, 'show'])->whereNumber('id')->name('attendance.detail');
    Route::get('/attendance/detail/date/{date}', [AttendanceDetailController::class, 'showByDate'])
        ->where('date', '\d{4}-\d{2}-\d{2}')
        ->name('attendance.detail.by_date');
    Route::post('/attendance/detail/{id}', [AttendanceDetailController::class, 'requestCorrection'])->whereNumber('id')->name('attendance.request');

    Route::get('/stamp_correction_request/list', function () {
        $user = auth()->user();
        if ($user && $user->role === 'admin') {
            return app(AdminController::class)->adminRequestPending(request());
        }
        return app(UserRequestListController::class)->pending(request());
    })->name('requests.pending');

    Route::get('/stamp_correction_request/list/approved', function () {
        $user = auth()->user();
        if ($user && $user->role === 'admin') {
            return app(AdminController::class)->adminRequestApproved(request());
        }
        return app(UserRequestListController::class)->approved(request());
    })->name('requests.approved');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/admin/attendance/list', [AdminController::class, 'adminAttendanceList'])->name('admin.attendance.list');
    Route::get('/admin/attendance/{id}', [AdminController::class, 'adminAttendanceShow'])->whereNumber('id')->name('admin.detail');
    Route::post('/admin/attendance/{id}', [AdminController::class, 'adminAttendanceUpdate'])->whereNumber('id')->name('admin.update');
    Route::get('/stamp_correction_request/approve/{attendance_correct_request}', [AdminController::class, 'showApproveRequest'])->name('admin.approve');
    Route::post('/stamp_correction_request/approve/{attendance_correct_request}', [AdminController::class, 'approveCorrectionRequest'])->name('admin.approve');

    Route::get('/admin/staff/list', [AdminController::class, 'adminStaffList'])->name('admin.staff.list');
    Route::get('/admin/attendance/staff/{id}', [AdminController::class, 'adminStaffAttendanceList'])->whereNumber('id')->name('admin.staff.attendance.list');
    Route::get('/admin/attendance/staff/{id}/csv', [AdminController::class, 'exportStaffAttendanceCsv'])->name('admin.staff.attendance.csv');

    Route::get('/email/verify', fn () => view('auth.verify-email'))->name('verification.notice');

    Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
        $request->fulfill();
        return redirect('/email/verified')->with('success', 'メール認証が完了しました！');
    })->middleware(['auth', 'signed'])->name('verification.verify');

    Route::post('/email/verification-notification', function (Request $request) {
        $request->user()->sendEmailVerificationNotification();
        return back()->with('status', 'verification-link-sent');
    })->middleware(['auth', 'throttle:6,1'])->name('verification.send');

    Route::get('/email/verified', fn () => view('auth.verified'))->middleware(['auth'])->name('email.verified');
});

Route::get('/logout', function () {
    Auth::logout();
    return redirect('/login');
});
Route::post('/logout', function () {
    Auth::logout();
    return redirect('/login');
})->name('logout');

Route::get('/register', fn () => view('auth.register'))->name('register');
Route::post('/register', [RegisterController::class, 'register']);
Route::get('/login', fn () => view('auth.login'))->name('login');
Route::post('/login', [AuthenticatedSessionController::class, 'store']);

Route::get('/admin/login', fn () => view('auth.admin_login'))->name('admin.login');
Route::post('/admin/login', [AuthenticatedSessionController::class, 'adminLogin']);