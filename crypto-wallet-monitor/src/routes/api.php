<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\WalletBalanceController;
use App\Http\Controllers\WalletHistoryController;
use App\Http\Controllers\WalletTransactionController;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\PriceController;
use App\Http\Controllers\NewsController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink']);
Route::post('/reset-password', [PasswordResetController::class, 'reset']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/wallets', [WalletController::class, 'index']);
    Route::post('/wallets', [WalletController::class, 'store']);
    Route::patch('/wallets/{id}', [WalletController::class, 'update']);
    Route::delete('/wallets/{id}', [WalletController::class, 'destroy']);
    Route::get('/prices', [PriceController::class, 'index']);
    Route::get('/portfolio/history', [PortfolioController::class, 'history']);
    Route::get('/news', [NewsController::class, 'index']);
});

Route::middleware('auth:sanctum')->get(
    '/wallets/{id}/balance',
    [WalletBalanceController::class, 'show']
);

Route::middleware('auth:sanctum')->get(
    '/wallets/{id}/history',
    [WalletHistoryController::class, 'index']
);

Route::middleware('auth:sanctum')->get(
    '/wallets/{id}/transactions',
    [WalletTransactionController::class, 'index']
);