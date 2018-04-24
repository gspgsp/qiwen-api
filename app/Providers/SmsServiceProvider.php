<?php

namespace App\Providers;

use App\Services\Sms\YPSmsService;
//use App\YPVoiceService;
use Illuminate\Support\ServiceProvider;

class SmsServiceProvider extends ServiceProvider {
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register() {

        $this->app->singleton('App\Services\Sms\SmsService', function($app){
            return new YPSmsService();
        });

//        $this->app->singleton('App\YPVoiceService', function($app){
//            return new YPVoiceService();
//        });
    }
}
