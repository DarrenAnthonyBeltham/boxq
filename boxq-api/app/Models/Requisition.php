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
        'has_tax',
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
        'invoice_attachment',
        'invoice_amount',
        'paid_by',
        'paid_at',
        'xendit_disbursement_id',
        'vendor_bank_code',
        'vendor_account_number',
        'vendor_account_name'
    ];

    protected $casts = [
        'items' => 'array',
    ];
}