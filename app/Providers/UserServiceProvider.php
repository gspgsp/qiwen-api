<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\UserService;

class UserServiceProvider extends ServiceProvider {
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register() {

        $this->app->singleton('App\UserService', function($app){
            return new UserService();
        });

    }
}
