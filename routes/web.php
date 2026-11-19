<?php

use App\Http\Controllers\CompanyController;
use App\Http\Controllers\ConnectionCheckController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\SetupController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/setup', [SetupController::class, 'show'])->name('setup');
    Route::post('/setup', [SetupController::class, 'store']);
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'show'])->name('dashboard');
    Route::get('/company', [CompanyController::class, 'edit'])->name('company.edit');
    Route::put('/company', [CompanyController::class, 'update'])->name('company.update');
    Route::post('/company/check', [ConnectionCheckController::class, 'store'])->name('company.check');
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
});
