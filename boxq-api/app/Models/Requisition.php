<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Eloquent\SoftDeletes;

class Requisition extends Model
{
    use SoftDeletes; 

    protected $connection = 'mongodb';
    protected $collection = 'requisitions';

    protected $fillable = [
        'user_id', 'requester', 'department', 'justification', 'items', 
        'subtotal', 'has_tax', 'tax_amount', 'total_price', 'currency', 
        'exchange_rate', 'cost_centers', 'status', 'approval_stage', 
        'approval_token', 'attachment', 'is_over_budget', 'amount_paid',
        'invoice_attachment', 'invoice_amount', 'vendor_bank_code', 
        'vendor_account_number', 'vendor_account_name', 'paid_by', 
        'paid_at', 'reconciled_by', 'reconciled_at', 'xendit_disbursement_id'
    ];

    protected $casts = [
        'has_tax' => 'boolean',
        'is_over_budget' => 'boolean'
    ];
}