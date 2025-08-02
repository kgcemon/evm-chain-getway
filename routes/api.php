<?php

use App\Http\Controllers\api\Client\Auth\UserAuthController;
use App\Http\Controllers\api\Client\ClientWalletBalanceController;
use App\Http\Controllers\api\Invoice\InvoiceCreateController;
use App\Http\Controllers\api\Invoice\PaymentJobController;
use App\Http\Controllers\api\Single\Deposit;
use App\Http\Controllers\api\Single\Withdrawal;
use App\Services\CreateWallet;
use Illuminate\Support\Facades\Route;

Route::get('/user',[UserAuthController::class,'profile'])->middleware('auth:sanctum');

Route::get('my-balance',[ClientWalletBalanceController::class,'balanceList']);

//auth
Route::post('create-account', [UserAuthController::class, 'createAccount']);
Route::post('create-login', [UserAuthController::class, 'login']);

Route::post('/create_invoice',[InvoiceCreateController::class,'createInvoice']);
Route::get('/last-transactions', [PaymentJobController::class, 'Jobs']);
Route::post('/payout', [Withdrawal::class, 'payout']);
Route::post('/deposit', [Deposit::class, 'deposit']);
Route::post('create-wallet', [CreateWallet::class, 'createAddress']);
Route::middleware(['throttle:15,1'])->get('payments/{id}', [PaymentJobController::class, 'checkNewPayments']);
Route::get('invoice/{invoice_id}', [PaymentJobController::class, 'invoiceData']);
