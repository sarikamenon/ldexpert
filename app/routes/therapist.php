<?php

use App\Http\Controllers\Therapist\StudentController;
use App\Http\Controllers\Therapist\DashboardController;
use Illuminate\Support\Facades\Route;

// Therapist area
Route::middleware('role:therapist')
    ->name('therapist.')
    ->prefix('therapist')
    ->group(function () {
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('students', [StudentController::class, 'index'])->name('students.index');
        Route::get('students/create', [StudentController::class, 'create'])->name('students.create');
        Route::post('students', [StudentController::class, 'store'])->name('students.store');
        Route::get('students/{user}', [StudentController::class, 'show'])->name('students.show');
        Route::get('students/{user}/edit', [StudentController::class, 'edit'])->name('students.edit');
        Route::patch('students/{user}', [StudentController::class, 'update'])->name('students.update');
        Route::patch('students/{user}/status/activate', [StudentController::class, 'activate'])->name('students.activate');
        Route::patch('students/{user}/status/deactivate', [StudentController::class, 'deactivate'])->name('students.deactivate');
    });
