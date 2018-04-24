<?php
/**
 * Created by PhpStorm.
 * User: zc
 * Date: 2018/3/26
 * Time: 13:26
 */
use App\Services\Swoole\SwooleClient;

class swoole_test{
    protected  $sw;
    public function index(){
        $data = array(
            'action' => 'getIdNo',
            'url' => 'http://dev.zkadmin.com/uploadfile/2018/0323/20180323052058697.png'
        );
        $this->sw = new SwooleClient();
        $this->sw->connect('127.0.0.1',9501);
        $this->sw->send($data);
    }
}

$swoole = new swoole_test();
$swoole->index();
/*$data = array(
    'action' => 'getIdNo',
    'url' => 'http://dev.zkadmin.com/uploadfile/2018/0323/20180323052058697.png'
);
$cli = new SwooleClient();
$cli->connect('127.0.0.1', 9501);
$cli->send(json_encode($data));*/