<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class OcrController extends Controller
{
    public function scanInvoice(Request $request)
    {
        $user = $request->user();

        if (!in_array($user->role, ['finance', 'admin', 'vendor'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'invoice' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120'
        ]);

        $apiKey  = env('MINDEE_API_KEY');
        $modelId = env('MINDEE_MODEL_ID');

        if (empty($apiKey) || empty($modelId)) {
            return response()->json(['error' => 'ENV_MISSING'], 500);
        }

        try {
            $enqueueResponse = Http::withHeaders([
                'Authorization' => $apiKey,
            ])->attach(
                'file',
                file_get_contents($request->file('invoice')->getRealPath()),
                $request->file('invoice')->getClientOriginalName()
            )->post('https://api-v2.mindee.net/v2/products/extraction/enqueue', [
                'model_id' => $modelId,
            ]);

            if (!$enqueueResponse->successful()) {
                Log::error('OCR Enqueue failed: ' . $enqueueResponse->body());
                return response()->json(['error' => 'ENQUEUE_FAILED', 'message' => $enqueueResponse->json()], 500);
            }

            $jobData    = $enqueueResponse->json();
            $jobId      = $jobData['job']['id'] ?? null;
            $pollingUrl = $jobData['job']['polling_url'] ?? null;

            if (!$jobId || !$pollingUrl) {
                return response()->json(['error' => 'NO_JOB_ID'], 500);
            }

            return response()->json([
                'status'      => 'processing',
                'job_id'      => $jobId,
                'polling_url' => $pollingUrl,
            ], 202);

        } catch (Throwable $e) {
            Log::error('OCR ENQUEUE CRASH: ' . $e->getMessage());
            return response()->json(['error' => 'SDK_CRASH', 'message' => $e->getMessage()], 500);
        }
    }

    public function pollInvoice(Request $request)
    {
        $user = $request->user();

        if (!in_array($user->role, ['finance', 'admin', 'vendor'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate(['job_id' => 'required|string']);

        $apiKey = env('MINDEE_API_KEY');
        $jobId  = $request->input('job_id');

        try {
            $pollResponse = Http::withHeaders([
                'Authorization' => $apiKey,
            ])->get("https://api-v2.mindee.net/v2/jobs/{$jobId}");

            $pollData = $pollResponse->json();
            $status   = $pollData['job']['status'] ?? 'Processing';

            if ($status === 'Failed') {
                return response()->json(['error' => 'JOB_FAILED', 'message' => $pollData], 500);
            }

            if ($status !== 'Processed') {
                return response()->json(['status' => 'processing'], 202);
            }

            $resultUrl      = $pollData['job']['result_url'] ?? null;
            $resultResponse = Http::withHeaders([
                'Authorization' => $apiKey,
            ])->get($resultUrl);

            $result = $resultResponse->json();
            $fields = $result['inference']['result']['fields'] ?? [];

            $amount     = $fields['total_amount']['value']  ?? $fields['total_net']['value']   ?? null;
            $vendorName = $fields['supplier_name']['value'] ?? $fields['vendor_name']['value'] ?? null;
            $taxAmount  = $fields['total_tax']['value']     ?? $fields['tax_amount']['value']  ?? null;

            return response()->json([
                'status'      => 'done',
                'amount'      => $amount,
                'vendor_name' => $vendorName,
                'tax_amount'  => $taxAmount,
            ]);

        } catch (Throwable $e) {
            Log::error('OCR POLL CRASH: ' . $e->getMessage());
            return response()->json(['error' => 'POLL_CRASH', 'message' => $e->getMessage()], 500);
        }
    }
}