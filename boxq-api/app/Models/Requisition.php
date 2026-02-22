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
        'total_price',
        'status',
        'reason',
    ];

    protected $casts = [
        'items' => 'array',
    ];
}