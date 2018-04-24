<?php
/**
 * User: Eric
 * Date: 2017/7/26
 * Time: 18:16
 */
namespace App\Services\Swoole;

class SwooleClient {
    private $client;

    public function __construct() {
        $this->client = new \swoole_client(SWOOLE_SOCK_TCP);
    }

    public function connect() {
        $fp = $this->client->connect("127.0.0.1", 9501, 1);
        if( !$fp ) {
            echo "Error: {$fp->errMsg}[{$fp->errCode}]\n";
            return;
        }
        //$message = $this->client->recv();
        //echo "Get Message From Server:{$message}\n";
    }

    public function send($data) {
        $this->client->send( $data . "\r\n" );
    }
}