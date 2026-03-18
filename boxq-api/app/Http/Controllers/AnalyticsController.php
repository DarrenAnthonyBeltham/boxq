<?php

namespace App\Http\Controllers;

use App\Models\Requisition;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AnalyticsController extends Controller
{
    public function dashboard(Request $request)
    {
        $now = Carbon::now();
        $monthStart = $now->copy()->startOfMonth();
        $daysInMonth = $now->daysInMonth;
        $currentDay = $now->day;

        $allRequisitions = Requisition::all();

        $allRequisitions->map(function ($req) {
            $req->usd_value = $req->currency === 'IDR' ? ($req->total_price / max($req->exchange_rate, 1)) : $req->total_price;
            return $req;
        });

        $validStatuses = ['Approved', 'PO Created', 'Received', 'Processing Payment', 'Partially Paid', 'Paid', 'Reconciled'];

        $spentThisMonth = $allRequisitions->where('created_at', '>=', $monthStart)
            ->whereIn('status', $validStatuses)
            ->sum('usd_value');

        $dailySpends = array_fill(1, $daysInMonth, 0);
        foreach ($allRequisitions->where('created_at', '>=', $monthStart)->whereIn('status', $validStatuses) as $req) {
            $day = Carbon::parse($req->created_at)->day;
            $dailySpends[$day] += $req->usd_value;
        }

        $cumulative = 0;
        $actualTrend = [];
        $projectedTrend = [];
        $labels = [];

        for ($i = 1; $i <= $daysInMonth; $i++) {
            $labels[] = 'Day ' . $i;
            if ($i <= $currentDay) {
                $cumulative += $dailySpends[$i];
                $actualTrend[] = round($cumulative, 2);
                $projectedTrend[] = null;
            } else {
                $actualTrend[] = null;
            }
        }

        $runRate = $currentDay > 0 ? ($cumulative / $currentDay) : 0;
        $projectedTotal = $runRate * $daysInMonth;

        $projectedCumulative = $cumulative;
        if ($currentDay > 0) {
            $projectedTrend[$currentDay - 1] = round($cumulative, 2);
        }
        
        for ($i = $currentDay + 1; $i <= $daysInMonth; $i++) {
            $projectedCumulative += $runRate;
            $projectedTrend[] = round($projectedCumulative, 2);
        }

        $spendingByDept = $allRequisitions->whereIn('status', $validStatuses)
            ->groupBy('department')
            ->map->sum('usd_value');

        $vendorAnalysis = $allRequisitions->whereNotNull('vendor_account_name')
            ->whereIn('status', $validStatuses)
            ->groupBy('vendor_account_name')
            ->map->sum('usd_value')
            ->sortDesc()
            ->take(10);

        $categorySpend = [];
        foreach ($allRequisitions->whereIn('status', $validStatuses) as $req) {
            foreach ($req->items as $item) {
                $itemName = $item['name'];
                $itemUsdTotal = $req->currency === 'IDR' ? (($item['price'] * $item['qty']) / max($req->exchange_rate, 1)) : ($item['price'] * $item['qty']);
                if (!isset($categorySpend[$itemName])) {
                    $categorySpend[$itemName] = 0;
                }
                $categorySpend[$itemName] += $itemUsdTotal;
            }
        }
        arsort($categorySpend);
        $topCategories = array_slice($categorySpend, 0, 10);

        $bottlenecks = $allRequisitions->where('status', 'Paid')->map(function ($req) {
            $created = Carbon::parse($req->created_at);
            $paid = Carbon::parse($req->paid_at);
            return $paid->diffInDays($created);
        });
        $avgCycleTime = $bottlenecks->count() > 0 ? round($bottlenecks->average(), 1) : 0;

        $anomalies = [];
        $recent = $allRequisitions->where('created_at', '>=', Carbon::now()->subDays(30));
        $grouped = $recent->groupBy(function ($req) {
            return $req->department . '_' . $req->total_price;
        });

        foreach ($grouped as $key => $group) {
            if ($group->count() > 1) {
                $anomalies[] = [
                    'type' => 'Potential Duplicate',
                    'description' => 'Multiple requests from ' . $group->first()->department . ' with exact same total.',
                    'requests' => $group->pluck('_id')
                ];
            }
        }

        return response()->json([
            'total_spent_this_month_usd' => round($spentThisMonth, 2),
            'projected_total_month_spend' => round($projectedTotal, 2),
            'trend_labels' => $labels,
            'actual_trend' => $actualTrend,
            'projected_trend' => $projectedTrend,
            'spending_by_department' => $spendingByDept,
            'vendor_analysis' => $vendorAnalysis,
            'top_items' => $topCategories,
            'avg_cycle_time_days' => $avgCycleTime,
            'anomalies' => $anomalies
        ]);
    }

    public function auditLogs($id)
    {
        return response()->json(AuditLog::where('requisition_id', $id)->orderBy('created_at', 'desc')->get());
    }

    public function exportCsv(Request $request)
    {
        $requisitions = Requisition::orderBy('created_at', 'desc')->get();
        $csvData = "ID,Requester,Department,Status,Currency,Total Price,Exchange Rate,USD Equivalent,Amount Paid,Created At\n";

        foreach ($requisitions as $req) {
            $usdValue = $req->currency === 'IDR' ? ($req->total_price / max($req->exchange_rate, 1)) : $req->total_price;
            $csvData .= sprintf(
                "%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n",
                $req->id,
                str_replace(',', ' ', $req->requester),
                str_replace(',', ' ', $req->department),
                $req->status,
                $req->currency,
                $req->total_price,
                $req->exchange_rate,
                round($usdValue, 2),
                $req->amount_paid ?? 0,
                $req->created_at
            );
        }

        return response($csvData)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="boxq_financial_export.csv"');
    }
}