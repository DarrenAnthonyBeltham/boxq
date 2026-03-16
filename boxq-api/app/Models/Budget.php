<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;

class Budget extends Model
{
    use HasFactory;

    protected $fillable = [
        'department',
        'monthly_limit'
    ];
}