<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\UtilService;

class UtilServiceProvider extends ServiceProvider {
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register() {

        $this->app->singleton('App\UtilService', function($app){
            return new UtilService();
        });

    }
}
