<?php
namespace App\Http\Controllers;
use App\Services\Swoole\SwooleClient;
class  test{
    public function test_swoole(){
        $data = array(
            'action' => 'getIdNo',
            'url' => 'http://dev.zkadmin.com/uploadfile/2018/0323/20180323052058697.png'
        );
        $cli = new SwooleClient();
        $cli->connect('127.0.0.1',9501);
        $cli->send($data);
    }
}