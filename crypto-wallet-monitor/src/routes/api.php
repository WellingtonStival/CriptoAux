<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\WalletBalanceController;
use App\Http\Controllers\WalletHistoryController;
use App\Http\Controllers\WalletTransactionController;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\PriceController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\WalletTokenController;
use App\Http\Controllers\AssetsController;
use App\Http\Controllers\MarketController;
use App\Http\Controllers\TelegramController;
use App\Http\Controllers\AlertController;
use App\Http\Controllers\AccountController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink']);
Route::post('/reset-password', [PasswordResetController::class, 'reset']);
Route::post('/email/verify', [EmailVerificationController::class, 'verify']);
Route::post('/email/resend', [EmailVerificationController::class, 'resend'])
    ->middleware('throttle:3,10');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/wallets', [WalletController::class, 'index']);
    Route::post('/wallets', [WalletController::class, 'store']);
    Route::patch('/wallets/{id}', [WalletController::class, 'update']);
    Route::delete('/wallets/{id}', [WalletController::class, 'destroy']);
    Route::get('/prices', [PriceController::class, 'index']);
    Route::get('/portfolio/history', [PortfolioController::class, 'history']);
    Route::get('/news', [NewsController::class, 'index']);
    Route::get('/assets', [AssetsController::class, 'index']);
    Route::get('/wallets/{id}/tokens', [WalletTokenController::class, 'index']);
    Route::post('/wallets/{id}/tokens/sync', [WalletTokenController::class, 'sync']);
    Route::delete('/wallets/{id}/tokens/{tokenId}', [WalletTokenController::class, 'destroy']);
    Route::get('/market/overview', [MarketController::class, 'overview']);
    Route::get('/market/fear-greed/history', [MarketController::class, 'fearGreedHistory']);
    Route::get('/telegram/status', [TelegramController::class, 'status']);
    Route::post('/telegram/link-code', [TelegramController::class, 'generateLinkCode']);
    Route::post('/telegram/unlink', [TelegramController::class, 'unlink']);
    Route::get('/alerts', [AlertController::class, 'index']);
    Route::post('/alerts', [AlertController::class, 'store']);
    Route::patch('/alerts/{id}', [AlertController::class, 'update']);
    Route::delete('/alerts/{id}', [AlertController::class, 'destroy']);
    Route::get('/account', [AccountController::class, 'show']);
    Route::patch('/account', [AccountController::class, 'update']);
    Route::post('/account/password', [AccountController::class, 'updatePassword']);
    Route::delete('/account', [AccountController::class, 'destroy']);
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