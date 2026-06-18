<?php

namespace App\Providers;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\ServiceProvider;
use Stripe\Stripe;

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
        Stripe::setApiKey(config('services.stripe.secret'));

        Carbon::serializeUsing(fn (CarbonInterface $date): string => $date->format('Y-m-d\TH:i:s\Z'));
    }
}
