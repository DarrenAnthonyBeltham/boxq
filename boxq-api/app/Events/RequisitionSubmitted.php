<?php

namespace App\Events;

use App\Models\Requisition;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;

class RequisitionSubmitted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $requisition;

    public function __construct(Requisition $requisition)
    {
        $this->requisition = $requisition;
    }

    public function broadcastOn()
    {
        return new Channel('requisitions');
    }

    public function broadcastAs()
    {
        return 'requisition.created';
    }
}