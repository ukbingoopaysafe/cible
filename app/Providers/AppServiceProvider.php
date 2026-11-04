<?php

namespace App\Providers;

use App\Models\RentalBroker;
use App\Models\RentalCustomer;
use App\Models\ResaleBroker;
use App\Models\ResaleCustomer;
use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::morphMap([
            'rental_customer' => RentalCustomer::class,
            'resale_customer' => ResaleCustomer::class,
            'rental_broker' => RentalBroker::class,
            'resale_broker' => ResaleBroker::class,
        ]);
    }
}
