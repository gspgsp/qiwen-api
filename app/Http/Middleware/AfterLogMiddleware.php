<?php

namespace App\Http\Middleware;

use App\Services\Log\ApiLogService;
use Closure;
use Illuminate\Http\Request;


/**
 * 将接口返回的数据写入日志
 * Class AfterLogMiddleware
 * @package App\Http\Middleware
 *
 * @author Tokey
 * @version 1.0
 */
class AfterLogMiddleware
{
    public function __construct(ApiLogService $logService){
        $this->logService = $logService::getInstance();
    }

    public function handle(Request $request, Closure $next) {
        $response = $next($request);
        $logInfo = '[INFO] Uuid:'.$request->uuid.' End time:'.microtimeFormat('Y/m/d H:i:s:x', microtimeFloat()).' Inerface:'.$request->path().' Response:'.response()->content().PHP_EOL;
        if (strpos($response->content(), '<!DOCTYPE html>') === false) {
            $this->logService->info($logInfo);
        }

        return $response;
    }
}
