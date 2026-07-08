<?php

use App\Http\Controllers\EventScheduleController;
use App\Http\Controllers\StudentController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome', ['page' => 'dashboard']);
});

Route::get('/dashboard', fn () => view('welcome', ['page' => 'dashboard']))->name('dashboard');
Route::get('/students', [StudentController::class, 'index'])->name('students');
Route::get('/students/qr-print', [StudentController::class, 'printQr'])->name('students.qr-print');
Route::post('/students', [StudentController::class, 'store'])->name('students.store');
Route::post('/students/import', [StudentController::class, 'import'])->name('students.import');
Route::patch('/students/{student}', [StudentController::class, 'update'])->name('students.update');
Route::delete('/students/{student}', [StudentController::class, 'destroy'])->name('students.destroy');
Route::get('/qr-scanner', fn () => view('welcome', ['page' => 'scanner']))->name('scanner');
Route::get('/attendance-records', fn () => view('welcome', ['page' => 'records']))->name('records');
Route::get('/event-schedule', [EventScheduleController::class, 'index'])->name('schedule');
Route::post('/event-schedule', [EventScheduleController::class, 'store'])->name('schedule.store');
Route::patch('/event-schedule/{eventSchedule}', [EventScheduleController::class, 'update'])->name('schedule.update');
Route::delete('/event-schedule/{eventSchedule}', [EventScheduleController::class, 'destroy'])->name('schedule.destroy');
Route::get('/booths', fn () => view('welcome', ['page' => 'booths']))->name('booths');
