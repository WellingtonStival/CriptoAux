<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\WalletBalanceController;
use App\Http\Controllers\WalletHistoryController;
use App\Http\Controllers\PriceController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/wallets', [WalletController::class, 'index']);
    Route::post('/wallets', [WalletController::class, 'store']);
    Route::delete('/wallets/{id}', [WalletController::class, 'destroy']);
    Route::get('/prices', [PriceController::class, 'index']);
});

Route::middleware('auth:sanctum')->get(
    '/wallets/{id}/balance',
    [WalletBalanceController::class, 'show']
);

Route::middleware('auth:sanctum')->get(
    '/wallets/{id}/history',
    [WalletHistoryController::class, 'index']
);