<?php

namespace App\Providers;

use App\Services\Log\ApiLogService;
use Illuminate\Support\ServiceProvider;

class LogServiceProvider extends ServiceProvider {

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register() {

        $this->app->singleton('App\Services\Log\LogService', function($app){
            return new ApiLogService();
        });

    }
}