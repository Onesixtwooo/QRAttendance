<?php

use App\Http\Controllers\AttendanceRecordController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BoothController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventScheduleController;
use App\Http\Controllers\ScannerController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentPortalController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->name('login.store');
});
Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/my-attendance', [StudentPortalController::class, 'index'])->name('student.portal');
    Route::get('/my-attendance/qr-code', [StudentPortalController::class, 'qr'])->name('student.qr');
    Route::patch('/my-attendance/account', [StudentPortalController::class, 'updateAccount'])->name('student.account.update');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/users', [UserController::class, 'index'])->name('users');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::patch('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    Route::get('/students', [StudentController::class, 'index'])->name('students');
    Route::get('/students/{student}/qr-code', [StudentController::class, 'qr'])->name('students.qr');
    Route::post('/students', [StudentController::class, 'store'])->name('students.store');
    Route::post('/students/bulk', [StudentController::class, 'storeBulk'])->name('students.store-bulk');
    Route::post('/students/{student}/account', [StudentController::class, 'saveAccount'])->name('students.account');
    Route::post('/students/import', [StudentController::class, 'import'])->name('students.import');
    Route::delete('/students/bulk', [StudentController::class, 'destroySelected'])->name('students.destroy-selected');
    Route::patch('/students/{student}', [StudentController::class, 'update'])->name('students.update');
    Route::delete('/students/{student}', [StudentController::class, 'destroy'])->name('students.destroy');
    Route::get('/qr-scanner', [ScannerController::class, 'index'])->name('scanner');
    Route::post('/qr-scanner/scan', [ScannerController::class, 'scan'])->name('scanner.scan');
    Route::get('/attendance-records', [AttendanceRecordController::class, 'index'])->name('records');
    Route::get('/attendance-records/print', [AttendanceRecordController::class, 'print'])->name('records.print');
    Route::post('/attendance-records', [AttendanceRecordController::class, 'store'])->name('records.store');
    Route::patch('/attendance-records/{attendanceRecord}', [AttendanceRecordController::class, 'update'])->name('records.update');
    Route::delete('/attendance-records/{attendanceRecord}', [AttendanceRecordController::class, 'destroy'])->name('records.destroy');
    Route::get('/event-schedule', [EventScheduleController::class, 'index'])->name('schedule');
    Route::post('/event-schedule', [EventScheduleController::class, 'store'])->name('schedule.store');
    Route::patch('/event-schedule/{eventSchedule}', [EventScheduleController::class, 'update'])->name('schedule.update');
    Route::patch('/event-schedule/{eventSchedule}/scan-phase', [EventScheduleController::class, 'updateScanPhase'])->name('schedule.scan-phase');
    Route::patch('/event-schedule/{eventSchedule}/time-in', [EventScheduleController::class, 'updateTimeInOpen'])->name('schedule.time-in');
    Route::delete('/event-schedule/{eventSchedule}', [EventScheduleController::class, 'destroy'])->name('schedule.destroy');
    Route::get('/booths', [BoothController::class, 'index'])->name('booths');
    Route::get('/booths/statuses', [BoothController::class, 'statuses'])->name('booths.statuses');
    Route::post('/booths', [BoothController::class, 'store'])->name('booths.store');
    Route::patch('/booths/{booth}', [BoothController::class, 'update'])->name('booths.update');
    Route::delete('/booths/{booth}', [BoothController::class, 'destroy'])->name('booths.destroy');
    Route::post('/booths/heartbeat', [BoothController::class, 'heartbeat'])->name('booths.heartbeat');
});
