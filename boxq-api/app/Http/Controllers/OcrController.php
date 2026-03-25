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

        $apiKey = env('MINDEE_API_KEY');

        if (empty($apiKey)) {
            return response()->json(['error' => 'ENV_MISSING', 'message' => 'Missing API Key'], 500);
        }

        try {
            $file = $request->file('invoice');

            $response = Http::withoutVerifying()
                ->withHeaders([
                    'Authorization' => 'Token ' . $apiKey,
                ])->attach(
                    'document', fopen($file->getRealPath(), 'r'), $file->getClientOriginalName()
                )->post('https://api.mindee.net/v1/products/mindee/invoices/v4/predict');

            if ($response->failed()) {
                Log::error('Mindee API Rejected: ' . $response->body());
                return response()->json(['error' => 'MINDEE_REJECTED', 'message' => 'AI rejected document.'], 500);
            }

            $data = $response->json();
            $prediction = $data['document']['inference']['prediction'] ?? [];

            return response()->json([
                'amount' => $prediction['total_amount']['value'] ?? null,
                'vendor_name' => $prediction['supplier_name']['value'] ?? null,
                'tax_amount' => $prediction['total_tax']['value'] ?? null,
            ]);

        } catch (Throwable $e) {
            Log::error('OCR NATIVE CRASH: ' . $e->getMessage());
            return response()->json(['error' => 'NATIVE_CRASH', 'message' => $e->getMessage()], 500);
        }
    }
}