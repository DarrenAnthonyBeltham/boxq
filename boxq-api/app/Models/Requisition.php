<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Requisition extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'requisitions';

    protected $fillable = [
        'user_id',
        'requester',
        'department',
        'justification',
        'items',
        'subtotal',
        'tax_amount',
        'total_price',
        'currency',
        'exchange_rate',
        'cost_centers',
        'status',
        'approval_stage',
        'approval_token',
        'reason',
        'attachment',
        'is_over_budget',
        'invoice_attachment'
    ];

    protected $casts = [
        'items' => 'array',
    ];
}