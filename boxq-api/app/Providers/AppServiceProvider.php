<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Requisition;
use App\Observers\RequisitionObserver;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        Requisition::observe(RequisitionObserver::class);
    }
}