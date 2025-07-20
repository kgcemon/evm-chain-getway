<?php

use App\Http\Controllers\Invoice_system\InvoiceCreateController;
use App\Http\Controllers\Invoice_system\PaymentJobController;
use App\Http\Controllers\Single_wallet_transaction\Withdrawal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/create_invoice',[InvoiceCreateController::class,'createInvoice']);
Route::get('/last-transactions', [PaymentJobController::class, 'Jobs']);
Route::post('/payout', [Withdrawal::class, 'payout']);

