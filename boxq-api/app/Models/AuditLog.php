<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class AuditLog extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'audit_logs';

    protected $fillable = [
        'requisition_id',
        'user_id',
        'user_name',
        'action',
        'changes',
        'ip_address'
    ];

    protected $casts = [
        'changes' => 'array'
    ];
}