<?php

namespace App\Http\Middleware;

use App\Services\Log\ApiLogService;
use Closure;
use Illuminate\Http\Request;


/**
 * 调用接口之前将请求参数信息写入日志
 * Class BeforeLogMiddleware
 * @package App\Http\Middleware
 *
 * @author Tokey
 * @version 1.0
 */
class BeforeLogMiddleware
{

    public function __construct(ApiLogService $logService)
    {
        $this->logger = $logService::getInstance();
    }

    public function handle(Request $request, Closure $next)
    {
        $request->uuid = uuid();
        $logInfo = '[INFO] Uuid:'.$request->uuid.' Start time:'.microtimeFormat('Y/m/d H:i:s:x', microtimeFloat()).' Inerface:'.$request->path().' Request:'.json_encode($request->all()).PHP_EOL;
        $this->logger->info($logInfo);
        return $next($request);
    }
}
