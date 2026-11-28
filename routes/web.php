<?php

use App\Http\Controllers\CompanyController;
use App\Http\Controllers\ConnectionCheckController;
use App\Http\Controllers\ContractorController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\NumberingSeriesController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\SetupController;
use Illuminate\Support\Facades\Route;

Route::post('/locale', [LocaleController::class, 'update'])->name('locale');

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
    Route::get('/contractors', [ContractorController::class, 'index'])->name('contractors.index');
    Route::post('/contractors', [ContractorController::class, 'store'])->name('contractors.store');
    Route::put('/contractors/{contractor}', [ContractorController::class, 'update'])->name('contractors.update');
    Route::delete('/contractors/{contractor}', [ContractorController::class, 'destroy'])->name('contractors.destroy');
    Route::post('/invoices/preview', [InvoiceController::class, 'preview'])->name('invoices.preview');
    Route::post('/invoices/{invoice}/issue', [InvoiceController::class, 'issue'])->name('invoices.issue');
    Route::post('/invoices/{invoice}/send', [InvoiceController::class, 'send'])->name('invoices.send');
    Route::post('/invoices/{invoice}/check', [InvoiceController::class, 'check'])->name('invoices.check');
    Route::post('/invoices/{invoice}/duplicate', [InvoiceController::class, 'duplicate'])->name('invoices.duplicate');
    Route::get('/invoices/{invoice}/upo', [InvoiceController::class, 'upo'])->name('invoices.upo');
    Route::get('/invoices/{invoice}/xml', [InvoiceController::class, 'xml'])->name('invoices.xml');
    Route::resource('invoices', InvoiceController::class);
    Route::get('/purchases', [PurchaseController::class, 'index'])->name('purchases.index');
    Route::post('/purchases/sync', [PurchaseController::class, 'sync'])->name('purchases.sync');
    Route::get('/purchases/{invoice}', [PurchaseController::class, 'show'])->name('purchases.show');
    Route::get('/purchases/{invoice}/xml', [PurchaseController::class, 'xml'])->name('purchases.xml');
    Route::resource('products', ProductController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('series', NumberingSeriesController::class)->only(['index', 'store', 'update', 'destroy'])->parameters(['series' => 'series']);
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
});
