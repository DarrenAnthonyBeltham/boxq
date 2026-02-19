<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Requisition extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'requisitions';

    protected $fillable = [
        'requester', 
        'department', 
        'items', 
        'total_price', 
        'status',
        'created_at'
    ];

    protected $casts = [
        'items' => 'array',
        'created_at' => 'datetime'
    ];
}