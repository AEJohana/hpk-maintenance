<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DailyCheckController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ReviewController;
use App\Http\Middleware\Role;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::view('/login', 'login')->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
});
Route::middleware(['auth', Role::class.':admin,maintenance,department'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/machines/{machine}', [DashboardController::class, 'machine'])->name('machines.show');
    Route::get('/inspections/{inspection}', [DailyCheckController::class, 'show'])->name('inspections.show');
    Route::get('/inspections/{inspection}/photos/{index}', [DailyCheckController::class, 'photo'])->whereNumber('index')->name('inspections.photo');
    Route::middleware(Role::class.':department')->group(function () {
        Route::view('/daily-check', 'scan')->name('daily');
        Route::post('/daily-check', [DailyCheckController::class, 'scan'])->name('daily.scan');
        Route::post('/inspections/{inspection}', [DailyCheckController::class, 'save'])->name('inspections.save');
        Route::post('/inspections/{inspection}/transfer', [DailyCheckController::class, 'transfer'])->name('inspections.transfer');
    });
    Route::middleware(Role::class.':admin,maintenance')->group(function () {
        Route::get('/reviews', [ReviewController::class, 'index'])->name('reviews');
        Route::post('/reviews/inspections/{inspection}', [ReviewController::class, 'inspection'])->name('reviews.inspection');
        Route::post('/reviews/transfers/{transfer}', [ReviewController::class, 'transfer'])->name('reviews.transfer');
    });
    Route::middleware(Role::class.':admin')->group(function () {
        Route::get('/admin', [AdminController::class, 'index'])->name('admin');
        Route::post('/admin/{type}', [AdminController::class, 'save'])->name('admin.save');
    });
});
