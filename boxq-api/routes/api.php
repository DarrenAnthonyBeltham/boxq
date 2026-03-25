<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RequisitionController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\XenditWebhookController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\VendorPortalController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\OcrController;
use App\Http\Middleware\MongoAuthMiddleware;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/requisitions/{id}/email-approval', [RequisitionController::class, 'emailApproval']);

Route::any('/webhooks/xendit', [XenditWebhookController::class, 'handleDisbursement']);

Route::middleware([MongoAuthMiddleware::class])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::put('/users/{id}', [UserController::class, 'update']);
    Route::post('/user/delegate', [UserController::class, 'setDelegation']);
    
    Route::put('/user/password', [ProfileController::class, 'updatePassword']);
    Route::post('/user/avatar', [ProfileController::class, 'uploadAvatar']);
    Route::put('/user/preferences', [ProfileController::class, 'updatePreferences']);

    Route::get('/requisitions', [RequisitionController::class, 'index']);
    Route::post('/requisitions', [RequisitionController::class, 'store']);
    Route::get('/requisitions/{id}', [RequisitionController::class, 'show']);
    Route::patch('/requisitions/{id}/status', [RequisitionController::class, 'updateStatus']);
    Route::post('/requisitions/{id}/assign-vendor', [RequisitionController::class, 'assignVendor']);
    Route::post('/requisitions/{id}/invoice', [RequisitionController::class, 'uploadInvoice']);
    Route::get('/requisitions/{id}/po', [RequisitionController::class, 'downloadPoPdf']);
    Route::post('/requisitions/{id}/send-po', [RequisitionController::class, 'sendPoToVendor']);
    Route::get('/requisitions/{id}/file/{type}', [RequisitionController::class, 'downloadFile']);
    Route::post('/requisitions/{id}/recall', [RequisitionController::class, 'recall']);

    Route::post('/ocr/scan-invoice', [OcrController::class, 'scanInvoice']);

    Route::get('/vendor-portal/orders', [VendorPortalController::class, 'index']);
    Route::get('/vendor-portal/orders/{id}', [VendorPortalController::class, 'show']);
    Route::post('/vendor-portal/orders/{id}/invoice', [VendorPortalController::class, 'uploadInvoice']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/read', [NotificationController::class, 'markAsRead']);

    Route::get('/budgets', [App\Http\Controllers\BudgetController::class, 'index']);
    Route::get('/budget/current', [App\Http\Controllers\BudgetController::class, 'current']);
    Route::post('/budgets', [App\Http\Controllers\BudgetController::class, 'store']);

    Route::get('/grn', [App\Http\Controllers\GoodsReceiptController::class, 'index']);
    Route::post('/grn', [App\Http\Controllers\GoodsReceiptController::class, 'store']);

    Route::get('/analytics/dashboard', [AnalyticsController::class, 'dashboard']);
    Route::get('/analytics/export', [AnalyticsController::class, 'exportCsv']);
    Route::get('/requisitions/{id}/audit-logs', [AnalyticsController::class, 'auditLogs']);

    Route::get('/vendors', [App\Http\Controllers\VendorController::class, 'index']);
    Route::post('/vendors', [App\Http\Controllers\VendorController::class, 'store']);
    Route::get('/purchase-orders', [App\Http\Controllers\PurchaseOrderController::class, 'index']);
    Route::post('/purchase-orders', [App\Http\Controllers\PurchaseOrderController::class, 'store']);

    Route::apiResource('products', ProductController::class);
});