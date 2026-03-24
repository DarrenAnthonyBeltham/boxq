<?php

namespace App\Http\Controllers;

use App\Models\Requisition;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use App\Services\AnalyticsService;

class AnalyticsController extends Controller
{
    protected $analyticsService;

    public function __construct(AnalyticsService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
    }

    public function dashboard(Request $request)
    {
        $stats = $this->analyticsService->getDashboardStats();
        return response()->json($stats);
    }

    public function auditLogs(Request $request, $id)
    {
        $user = $request->user();
        $requisition = Requisition::findOrFail($id);

        if (!in_array($user->role, ['admin', 'finance']) && $requisition->user_id !== $user->id) {
            if (!($user->role === 'manager' && $requisition->department === $user->department)) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        $logs = AuditLog::where('requisition_id', $id)
                        ->orderBy('created_at', 'desc')
                        ->get();

        return response()->json($logs);
    }

    public function exportCsv(Request $request)
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="boxq_financial_export.csv"',
        ];

        $callback = function() {
            $file = fopen('php://output', 'w');
            
            fputcsv($file, ['ID', 'Requester', 'Department', 'Status', 'Currency', 'Total Price', 'Exchange Rate', 'USD Equivalent', 'Amount Paid', 'Created At']);

            foreach (Requisition::orderBy('created_at', 'desc')->cursor() as $req) {
                $rate = max((float) $req->exchange_rate, 1);
                $usdValue = $req->currency === 'IDR' ? ((float) $req->total_price / $rate) : (float) $req->total_price;
                
                fputcsv($file, [
                    $req->id,
                    $req->requester,
                    $req->department,
                    $req->status,
                    $req->currency,
                    $req->total_price,
                    $req->exchange_rate,
                    round($usdValue, 2),
                    $req->amount_paid ?? 0,
                    $req->created_at
                ]);
            }
            
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}