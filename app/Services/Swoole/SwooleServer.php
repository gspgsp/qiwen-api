<?php
require_once (__DIR__.'/../../../bootstrap/app.php');
require_once (__DIR__.'/../../../vendor/baiduyun/AipOcr.php');
class Server
{
    private $serv;

    public function __construct() {
        //swoole服务端初始化
        $this->serv = new swoole_server("0.0.0.0", 9501);
        $this->serv->set(array(
            'worker_num' => 8,
            'daemonize' => false,
            'max_request' => 10000,
            'dispatch_mode' => 2,
            'debug_mode'=> 1,
            'task_worker_num' => 8,
            'open_eof_split' => true, //打开EOF_SPLIT检测
            'package_eof' => "\r\n", //设置EOF
        ));
        $this->serv->on('Start', array($this, 'onStart'));
        $this->serv->on('Connect', array($this, 'onConnect'));
        $this->serv->on('Receive', array($this, 'onReceive'));
        $this->serv->on('Close', array($this, 'onClose'));
        // bind callback
        $this->serv->on('Task', array($this, 'onTask'));
        $this->serv->on('Finish', array($this, 'onFinish'));
        $this->serv->start();
    }

    public function onStart( $serv ) {
        echo "Start\n";
    }

    public function onConnect( $serv, $fd, $from_id ) {
        echo "Client {$fd} connect\n";
    }

    public function onReceive( swoole_server $serv, $fd, $from_id, $data ) {
        echo "Get Message From Client {$fd}:{$data}\n";
        // send a task to task worker.
        $serv->task($data);

        echo "Continue Handle Worker\n";
    }

    public function onClose( $serv, $fd, $from_id ) {
        echo "Client {$fd} close connection\n";
    }

    public function onTask($serv,$task_id,$from_id, $data) {
        echo "This Task {$task_id} from Worker {$from_id}\n";
        echo "Data: {$data}\n";

        $data = json_decode($data, true);
        $action = $data['action'];
        unset($data['action']);
        call_user_func(array($this, $action), $data);
        //$this->grab_deal($data);
        //$serv->send($fd, "Data in Task {$task_id}");
        return "Task {$task_id}'s result";
    }

    public function onFinish($serv,$task_id, $data) {
        echo "Task {$task_id} finish\n";
        echo "Result: {$data}\n";
    }
    public function getIdNo($data){
        $client =  new  \AipOcr('10983027','gaIk9sKWXTL4tR1C5CQQLW6g','3cAbOQY1HxchU6iKZ73tsOqgxfPHSGUq');
        $image = file_get_contents($data['url']);
        $idCardSide = "front";
        // 调用身份证识别
        $client->idcard($image, $idCardSide);
        // 如果有可选参数
        $options = array();
        $options["detect_direction"] = "false";
        $options["detect_risk"] = "false";
        // 带参数调用身份证识别
        $idno = $client->idcard($image, $idCardSide, $options);
        if(!empty($idno)){
            //DB::table("information")->where("id",'=',$data['id'])->update(array('idno'=>$idno['words_result']['公民身份号码']['words']));
             app('db')->select("update v9_information set idno = ".$idno['words_result']['公民身份号码']['words']." where id =".$data['id']);
        }
    }


}
$server = new Server();
