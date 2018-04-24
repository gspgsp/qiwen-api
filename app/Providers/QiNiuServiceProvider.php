<?php
/**
 * Created by PhpStorm.
 * User: txz
 * Date: 2018/1/5
 * Time: 17:02
 */
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\QiNiuService;

class QiNiuServiceProvider extends ServiceProvider {
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // TODO: Implement register() method.
        $this->app->singleton('App\Services\QiNiuService',function ($app){
            return new QiNiuService();
        });
    }
}