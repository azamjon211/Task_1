<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\ProviderController;
use App\Http\Controllers\RefundController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\StorageController;

Route::post('/purchases', [PurchaseController::class, 'store']);
Route::get('/providers', [ProviderController::class, 'index']);
Route::post('/providers', [ProviderController::class, 'store']);
Route::get('/categories', [CategoryController::class, 'index']);
Route::post('/categories', [CategoryController::class, 'store']);
Route::get('/products', [ProductController::class, 'index']);
Route::post('/products', [ProductController::class, 'store']);
Route::get('/products/available', [ProductController::class, 'available']);
Route::get('/storages', [StorageController::class, 'index']);
Route::post('/storages', [StorageController::class, 'store']);
Route::get('/storages/remaining', [ReportController::class, 'remainingQuantities']);
Route::get('/clients', [ClientController::class, 'index']);
Route::post('/clients', [ClientController::class, 'store']);

Route::prefix('batches')->group(function () {
    Route::post('/refund', [RefundController::class, 'refundBatch']);
    Route::get('/profit', [ReportController::class, 'batchProfit']);
});
Route::group(['prefix' =>'orders'], function(){
    Route::post('/', [OrderController::class, 'store']);
    Route::post('/refund', [RefundController::class, 'refundOrder']);
});

