<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RequisitionController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProductController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/requisitions/{id}/email-approval', [App\Http\Controllers\RequisitionController::class, 'emailApproval']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::get('/users', [App\Http\Controllers\UserController::class, 'index']);
    Route::put('/user/password', [ProfileController::class, 'updatePassword']);
    Route::post('/user/avatar', [ProfileController::class, 'uploadAvatar']);
    Route::put('/user/preferences', [ProfileController::class, 'updatePreferences']);

    Route::get('/requisitions', [RequisitionController::class, 'index']);
    Route::post('/requisitions', [RequisitionController::class, 'store']);
    Route::get('/requisitions/{id}', [RequisitionController::class, 'show']);
    Route::patch('/requisitions/{id}/status', [RequisitionController::class, 'updateStatus']);
    
    Route::post('/user/delegate', [App\Http\Controllers\UserController::class, 'setDelegation']);

    Route::get('/vendors', [App\Http\Controllers\VendorController::class, 'index']);
    Route::post('/vendors', [App\Http\Controllers\VendorController::class, 'store']);
    Route::get('/purchase-orders', [App\Http\Controllers\PurchaseOrderController::class, 'index']);
    Route::post('/purchase-orders', [App\Http\Controllers\PurchaseOrderController::class, 'store']);

    Route::apiResource('products', ProductController::class);
});