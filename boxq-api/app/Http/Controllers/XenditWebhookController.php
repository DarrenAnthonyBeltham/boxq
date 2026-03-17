<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Requisition;
use Illuminate\Support\Facades\Log;

class XenditWebhookController extends Controller
{
    public function handleDisbursement(Request $request)
    {
        $xenditToken = env('XENDIT_WEBHOOK_TOKEN');

        if (empty($xenditToken) || $request->header('x-callback-token') !== $xenditToken) {
            Log::warning('Unauthorized Xendit Webhook Attempt', ['ip' => $request->ip()]);
            return response()->json(['message' => 'Unauthorized Webhook Signature'], 403);
        }

        $externalId = $request->input('external_id');
        $status = $request->input('status');

        if (!$externalId) {
            return response()->json(['success' => false], 400);
        }

        $parts = explode('_', $externalId);
        
        if (count($parts) >= 2 && $parts[0] === 'req') {
            $reqId = $parts[1];
            $requisition = Requisition::find($reqId);

            if ($requisition) {
                if ($status === 'COMPLETED') {
                    $targetAmount = $requisition->currency === 'IDR' 
                        ? ($requisition->invoice_amount ?? $requisition->total_price) 
                        : (($requisition->invoice_amount ?? $requisition->total_price) * $requisition->exchange_rate);

                    if ($requisition->amount_paid >= $targetAmount) {
                        $requisition->status = 'Paid';
                    } else {
                        $requisition->status = 'Partially Paid';
                    }
                } elseif ($status === 'FAILED') {
                    $requisition->status = 'Payment Failed';
                }
                $requisition->save();
            }
        }

        return response()->json(['success' => true]);
    }
}