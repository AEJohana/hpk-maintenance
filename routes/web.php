<?php

use App\Http\Controllers\AccessController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DailyCheckController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ReviewController;
use App\Http\Middleware\AccountAccess;
use App\Models\TransferRequest;
use App\Services\Access;
use App\Services\Modules;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::view('/login', 'login')->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
});
Route::middleware(['auth', AccountAccess::class])->group(function () {
    Route::get('/access', [AccessController::class, 'index'])->name('access');
    Route::post('/access/{type}', [AccessController::class, 'save'])->name('access.save');
    Route::get('/audit', [AccessController::class, 'audit'])->name('audit');
    Route::get('/', function () {
        $modules = collect(Modules::all())->filter(fn ($module, $key) => auth()->user()->hasModule($key) && ($key !== 'daily' || auth()->user()->department_id));
        $pendingTransfers = auth()->user()->can('transfers.decide') ? Access::scope(TransferRequest::query())->where('status', 'pending')->count() : 0;

        return view('home', compact('modules', 'pendingTransfers'));
    })->name('home');
    Route::get('/monitoring', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/machines/{machine}', [DashboardController::class, 'machine'])->name('machines.show');
    Route::get('/inspections/{inspection}', [DailyCheckController::class, 'show'])->name('inspections.show');
    Route::get('/inspections/{inspection}/photos/{index}', [DailyCheckController::class, 'photo'])->whereNumber('index')->name('inspections.photo');
    Route::group([], function () {
        Route::view('/daily-check', 'scan')->middleware('can:inspections.scan')->name('daily');
        Route::post('/daily-check', [DailyCheckController::class, 'scan'])->name('daily.scan');
        Route::post('/inspections/{inspection}', [DailyCheckController::class, 'save'])->name('inspections.save');
        Route::post('/inspections/{inspection}/transfer', [DailyCheckController::class, 'transfer'])->name('inspections.transfer');
    });
    Route::group([], function () {
        Route::get('/reviews', [ReviewController::class, 'index'])->name('reviews');
        Route::post('/reviews/inspections/{inspection}', [ReviewController::class, 'inspection'])->name('reviews.inspection');
        Route::post('/reviews/transfers/{transfer}', [ReviewController::class, 'transfer'])->name('reviews.transfer');
    });
    Route::group([], function () {
        Route::get('/admin', [AdminController::class, 'index'])->name('admin');
        Route::post('/admin/{type}', [AdminController::class, 'save'])->name('admin.save');
    });
});
