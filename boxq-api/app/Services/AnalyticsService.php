<?php

namespace App\Services;

use App\Models\Requisition;
use Carbon\Carbon;

class AnalyticsService
{
    private $validStatuses = ['Approved', 'PO Created', 'Received', 'Processing Payment', 'Partially Paid', 'Paid', 'Reconciled'];

    public function getDashboardStats()
    {
        $now = Carbon::now();
        $monthStart = $now->copy()->startOfMonth();

        $allRequisitions = Requisition::select(
            'department', 'total_price', 'currency', 'exchange_rate', 
            'status', 'created_at', 'paid_at', 'vendor_account_name', 'items', '_id'
        )->get();

        $allRequisitions->transform(function ($req) {
            $rate = max((float) $req->exchange_rate, 1);
            $req->usd_value = $req->currency === 'IDR' ? ((float) $req->total_price / $rate) : (float) $req->total_price;
            
            if (is_string($req->items)) {
                $req->items = json_decode($req->items, true);
            }
            
            return $req;
        });

        $validRequisitions = $allRequisitions->whereIn('status', $this->validStatuses);

        return [
            'total_spent_this_month_usd' => $this->calculateMtdSpend($validRequisitions, $monthStart),
            ...$this->calculateBurnRate($validRequisitions, $monthStart, $now->daysInMonth, $now->day),
            'spending_by_department' => $this->calculateDepartmentSpend($validRequisitions),
            'vendor_analysis' => $this->calculateVendorSpend($validRequisitions),
            'top_items' => $this->calculateCategorySpend($validRequisitions),
            'avg_cycle_time_days' => $this->calculateCycleTime($allRequisitions),
            'anomalies' => $this->detectAnomalies($allRequisitions)
        ];
    }

    private function calculateMtdSpend($requisitions, $monthStart)
    {
        return round($requisitions->where('created_at', '>=', $monthStart)->sum('usd_value'), 2);
    }

    private function calculateBurnRate($requisitions, $monthStart, $daysInMonth, $currentDay)
    {
        $dailySpends = array_fill(1, $daysInMonth, 0);
        foreach ($requisitions->where('created_at', '>=', $monthStart) as $req) {
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

        return [
            'projected_total_month_spend' => round($projectedTotal, 2),
            'trend_labels' => $labels,
            'actual_trend' => $actualTrend,
            'projected_trend' => $projectedTrend,
        ];
    }

    private function calculateDepartmentSpend($requisitions)
    {
        return $requisitions->groupBy('department')->map->sum('usd_value');
    }

    private function calculateVendorSpend($requisitions)
    {
        return $requisitions->whereNotNull('vendor_account_name')
            ->where('vendor_account_name', '!=', '')
            ->groupBy('vendor_account_name')
            ->map->sum('usd_value')
            ->sortDesc()
            ->take(10);
    }

    private function calculateCategorySpend($requisitions)
    {
        $categorySpend = [];
        foreach ($requisitions as $req) {
            if (!is_array($req->items)) continue;

            $rate = max((float) $req->exchange_rate, 1);
            foreach ($req->items as $item) {
                if (!isset($item['name']) || !isset($item['price']) || !isset($item['qty'])) continue;

                $itemName = $item['name'];
                $itemUsdTotal = $req->currency === 'IDR' ? (((float) $item['price'] * (float) $item['qty']) / $rate) : ((float) $item['price'] * (float) $item['qty']);
                
                if (!isset($categorySpend[$itemName])) {
                    $categorySpend[$itemName] = 0;
                }
                $categorySpend[$itemName] += $itemUsdTotal;
            }
        }
        arsort($categorySpend);
        return array_slice($categorySpend, 0, 10);
    }

    private function calculateCycleTime($requisitions)
    {
        $bottlenecks = $requisitions->where('status', 'Paid')->map(function ($req) {
            if (!$req->paid_at) return null;
            return Carbon::parse($req->created_at)->diffInDays(Carbon::parse($req->paid_at));
        })->filter(function($val) { return $val !== null; });

        return $bottlenecks->count() > 0 ? round($bottlenecks->average(), 1) : 0;
    }

    private function detectAnomalies($requisitions)
    {
        $anomalies = [];
        $thirtyDaysAgo = Carbon::now()->subDays(30);

        $recent = $requisitions->filter(function ($req) use ($thirtyDaysAgo) {
            return Carbon::parse($req->created_at)->gte($thirtyDaysAgo);
        });

        $grouped = $recent->groupBy(function ($req) {
            return $req->department . '_' . $req->total_price;
        });

        foreach ($grouped as $group) {
            if ($group->count() > 1) {
                $anomalies[] = [
                    'type' => 'Potential Duplicate',
                    'description' => 'Multiple requests from ' . $group->first()->department . ' with exact same total.',
                    'requests' => $group->map(function($req) { return (string) ($req->id ?? $req->_id); })->toArray()
                ];
            }
        }
        return $anomalies;
    }
}