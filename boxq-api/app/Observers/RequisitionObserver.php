<?php

namespace App\Observers;

use App\Models\Requisition;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class RequisitionObserver
{
    public function created(Requisition $requisition)
    {
        $this->log($requisition, 'Created', ['new' => $requisition->toArray()]);
    }

    public function updated(Requisition $requisition)
    {
        $changes = $requisition->getDirty();
        $original = $requisition->getOriginal();
        $logData = [];

        foreach ($changes as $key => $value) {
            if ($key !== 'updated_at') {
                $logData[$key] = [
                    'old' => $original[$key] ?? null,
                    'new' => $value
                ];
            }
        }

        if (!empty($logData)) {
            $this->log($requisition, 'Updated', $logData);
        }
    }

    public function deleted(Requisition $requisition)
    {
        $this->log($requisition, 'Deleted', ['old' => $requisition->toArray()]);
    }

    private function log(Requisition $requisition, $action, $changes)
    {
        $user = Auth::user();

        AuditLog::create([
            'requisition_id' => $requisition->id,
            'user_id' => $user ? $user->id : 'System',
            'user_name' => $user ? $user->name : 'System Webhook',
            'action' => $action,
            'changes' => $changes,
            'ip_address' => Request::ip()
        ]);
    }
}