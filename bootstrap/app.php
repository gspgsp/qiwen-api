<?php

require_once __DIR__.'/../vendor/autoload.php';

try {
    (new Dotenv\Dotenv(__DIR__.'/../'))->load();
} catch (Dotenv\Exception\InvalidPathException $e) {
    //
}

/*
|--------------------------------------------------------------------------
| Create The Application
|--------------------------------------------------------------------------
|
| Here we will load the environment and create the application instance
| that serves as the central piece of this framework. We'll use this
| application as an "IoC" container and router for this framework.
|
*/

$app = new Laravel\Lumen\Application(
    realpath(__DIR__.'/../')
);

$app->withFacades();

$app->withEloquent();

$app->configure('app');
$app->configure('token');
$app->configure('cors');
$app->configure('qiniu');
$app->configure('moxie');
$app->configure('geetest');
/*
|--------------------------------------------------------------------------
| Register Container Bindings
|--------------------------------------------------------------------------
|
| Now we will register a few bindings in the service container. We will
| register the exception handler and the console kernel. You may add
| your own bindings here if you like or you can make another file.
|
*/

$app->singleton(
    Illuminate\Contracts\Debug\ExceptionHandler::class,
    App\Exceptions\Handler::class
);

$app->singleton(
    Illuminate\Contracts\Console\Kernel::class,
    App\Console\Kernel::class
);

/*
|--------------------------------------------------------------------------
| Register Middleware
|--------------------------------------------------------------------------
|
| Next, we will register the middleware with the application. These can
| be global middleware that run before and after each request into a
| route or middleware that'll be assigned to some specific routes.
|
*/
$app->middleware([
    KamiOrz\Cors\CorsMiddleware::class,
]);

$app->routeMiddleware([
    'token' => App\Http\Middleware\TokenAuthenticate::class,
    'xss'  => App\Http\Middleware\XSSProtection::class,
    'beforeLog'  => App\Http\Middleware\BeforeLogMiddleware::class,
    'afterLog'  => App\Http\Middleware\AfterLogMiddleware::class,
]);
// $app->middleware([
//    App\Http\Middleware\ExampleMiddleware::class
// ]);

// $app->routeMiddleware([
//     'auth' => App\Http\Middleware\Authenticate::class,
// ]);

/*
|--------------------------------------------------------------------------
| Register Service Providers
|--------------------------------------------------------------------------
|
| Here we will register all of the application's service providers which
| are used to bind services into the container. Service providers are
| totally optional, so you are not required to uncomment this line.
|
*/
$app->register(App\Providers\AppServiceProvider::class);
// $app->register(App\Providers\AuthServiceProvider::class);
// $app->register(App\Providers\EventServiceProvider::class);
$app->register(KamiOrz\Cors\CorsServiceProvider::class);
$app->register(Mnabialek\LaravelSqlLogger\Providers\ServiceProvider::class);
$app->register(App\Providers\LogServiceProvider::class);
$app->register(Illuminate\Redis\RedisServiceProvider::class);
$app->register(App\Providers\UserServiceProvider::class);
$app->register(App\Providers\UtilServiceProvider::class);
$app->register(App\Providers\SmsServiceProvider::class);
$app->register(App\Providers\QiNiuServiceProvider::class);
/*
|--------------------------------------------------------------------------
| Load The Application Routes
|--------------------------------------------------------------------------
|
| Next we will include the routes file so that they can all be added to
| the application. This will provide all of the URLs the application
| can respond to, as well as the controllers that may handle them.
|
*/

$app->group(['namespace' => 'App\Http\Controllers'], function ($app) {
    require __DIR__.'/../app/Http/routes.php';
});
$app->configureMonologUsing(function(Monolog\Logger $monolog) use ($app) {
    return $monolog->pushHandler(
        new \Monolog\Handler\RotatingFileHandler($app->storagePath().'/logs/lumen.log')
    );
});

return $app;
