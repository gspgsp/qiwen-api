<?php
//
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Response;
use App\Helper\Token;
use App\Helper\Protocol;

class TokenAuthenticate
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $token = Token::authorization();

        if ($token === false) {
            return response()->json([
                'status' => 400,
                'error' => [
                    'code' => '10001',
                    'message' => 'token无效!'
                ]
            ], env('CLIENT_ERROR_CODE', 400));

//            return show_error(10001, trans('message.token.invalid'));
        }

        if ($token ===  'token-expired') {
            return response()->json([
                'status' => 400,
                'error' => [
                    'code' => '10002',
                    'message' => 'token过期!'
                ]
            ], env('CLIENT_ERROR_CODE', 400));
//            return show_error(10002, trans('message.token.expired'));
        }

        return $next($request);
    }

}