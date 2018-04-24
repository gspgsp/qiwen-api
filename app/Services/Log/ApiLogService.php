<?php
namespace App\Services\Log;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

class ApiLogService implements LogService {

    private static $logger;

    public static function getInstance(){
        if (null === self::$logger) {
            // Create a handler
            $stream = new StreamHandler(storage_path('logs/api').DIRECTORY_SEPARATOR.'app_'.date('Y-m-d-H').'.log', Logger::INFO);
            $output = "%message%".PHP_EOL;
            // finally, create a formatter
            $formatter = new LineFormatter($output);
            $stream->setFormatter($formatter);
            // bind it to a logger object
            $logger = new Logger('interface');
            $logger->pushHandler($stream);
            self::$logger = $logger;
        }

        return self::$logger;
    }

    public function info($msg){
        self::$logger->addInfo($msg);
    }
}
