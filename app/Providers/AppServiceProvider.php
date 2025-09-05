<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\Purchase\PurchaseService;

class AppServiceProvider extends ServiceProvider
{
    private $map = [
        'App\Services\Purchase\PurchaseService'=>'App\Services\Purchase\PurchaseService'
    ];
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
        $this->app->singleton(PurchaseService::class, function ($app) {
            return new Connection(config('purchase'));
        });

        foreach ($this->map as $key => $value){
            if (class_exists($key) && class_exists($value)){
                $this->app->singleton($key, $value);
            }
        }
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
