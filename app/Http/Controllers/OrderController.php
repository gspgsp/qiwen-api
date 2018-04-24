<?php
/**
 * Created by PhpStorm.
 * User: zc
 * Date: 2017/12/29
 * Time: 14:09
 */
namespace App\Http\Controllers;

use App\Helper\Token;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use App\Services\UserService;
use App\Services\UtilService;
use App\Services\QiNiuService;
use App\Services\Swoole\SwooleClient;


class OrderController{
    public function __construct(UserService $userService, UtilService $utilService,QiNiuService $qiNiuService) {
        $this->userService = $userService;
        $this->utilService = $utilService;
        $this->qiNiuService = $qiNiuService;
    }
    public function index(){

    }
    public function getUploadToken(Request $request){
        $id = $request->input('id');
        if(empty($request->input('id'))){

        }
        if (empty($request->input('ext'))) {

        }
        if (empty($request->input('type'))) {

        }
        $type = $request->input('type');
        $userid = $this->userService->getUserId($request->input('access_token'));
        if (!$userid) {

        }
        switch ($type) {
            case 1:
                $name = 'idcard1';//证件正面
                break;
            case 2:
                $name = 'idcard2';//证件反面
                break;
            case 3:
                $name = 'payevidence';//手持证件
                break;
            case 4:
                $name = 'bankcard';//银行卡
                break;
        }
        $qiniu = $this->utilService->qiniuGetInstance();
        $key   = sprintf('user-%s-'.$name.'.%s', $userid, $request->input('ext'));
        $token = $qiniu->uploadToken(config('qiniu.qiniu_bucket'), $key, 3600, [
            // key的scope指定后, 默认上传insertOnly=0, 会自动覆盖
            'scope' => config('qiniu.qiniu_bucket').':'.$key,
        ]);
        $url = sprintf('%s/%s',config('qiniu.private_image_url'), $key);
        $update = DB::table('information')->where('id','=',$id)->update([$name=>$url]);
        if($update){
            return '~~';
        }else{
            return '~~';
        }
    }
    /*
     * productName 产品名称
     * name  客户名称
     * id  产品id
     * catid 产品类id
     *
     * */
    public function setpOne(Request $request){
        $userid = $this->userService->getUserId(Token::authorization());
        $productName  = $request->input('productname');
        $name = $request->input('name');
        $user_type  =$request->input('type');
        //$idno = $request->input('idno');
        $productId    = $request->input('productid');
        $productCatId = $request->input('catid');
        if(empty($productName) || empty($name) || empty($user_type) || empty($productId) || empty($productCatId) ){
            return response()->json([
                'status' => 500,
                'error' => [
                    'code' => '300001',
                    'message' => ' Parameter required.'
                ]
            ]);
        }
        if($userid > 0){
                try{
                    $table = Controller::getProductName($productCatId);
                    $cfp = DB::table('member')->select('username')->where("userid",$userid)->get();
                    $fundPeriod = DB::table($table)->select('fundPeriod')->where('id','=',$productId)->get();
                    $info_id =  DB::table('information')->insertGetId(['fundPeriod'=>$fundPeriod[0]->fundPeriod,'product'=>$productName,'name'=>$name,'cat_id'=>$productCatId,'product_id'=>$productId,'userid'=>$userid,'create_time'=>time(),'user_type'=>$user_type,'cfp'=>$cfp[0]->username]);
                    return response()->json(['status' => 200,
                        'data'=>array('id'=>$info_id)]);
                }catch (\Exception $e){
                    return response()->json([
                        'status' => 500,
                        'error' => [
                            'code' => '300000',
                            'message' => 'Internal server error.'
                        ]
                    ]);
                }
        }else{
            return response()->json([
                 'status' => 400,
                 'error' => [
                    'code' => '020000',
                    'message' => 'Login required.']
                    ]);
        }

    }
    /*
     *
     * info 产品id
     *
     * */

    public function setpTwo_GetRate(Request $request){
        $userid = $this->userService->getUserId(Token::authorization());
        if($userid > 0){
           try{
               $infoid    = $request->input('id');
               $infoData = DB::table('information')->select('product_id','cat_id')->where('id','=',$infoid)->get();
               $table = Controller::getProductName($infoData[0]->cat_id);
               $rate = DB::table($table)->select('rebate')->where('id','=',$infoData[0]->product_id)->get();
               $rate = json_decode($rate[0]->rebate);
               $arr =array();
               foreach ($rate as $k=>$v){
                   $arr[$k]['begin']= intval($v->begin);
                   $arr[$k]['end'] = intval($v->end);
                   $arr[$k]['rate'] = intval($v->rate);
               }
               return  response()->json([
                   'status'=>200,
                   'data'=>$arr]);
           }catch (\Exception $e){
               return response()->json([
                   'status' => 500,
                   'error' => [
                       'code' => '300003',
                       'message' => 'Internal server error.'
                   ]
               ]);
           }
        }else{
            return response()->json([
                'status' => 500,
                'error' => [
                    'code' => '300007',
                    'message' => 'Login required.'
                ]
            ]);
        }
    }

    public function setpTwo(Request $request){
        $userid = $this->userService->getUserId(Token::authorization());
        if($userid > 0){
            $id = intval($request->input('id'));
            $money = intval($request->input('money'));
            $comment = $request->input('comment');
            $compact_no = $request->input('compact_no');

            if(empty($money)|| empty($id) ){
                return response()->json([
                    'status' => 400,
                    'error' => [
                        'code' => '300004',
                        'message' => 'Parameter required.'
                    ]
                ]);
            }else{
                try{
                    $infoData = DB::table('information')->select('product_id','cat_id')->where('id','=',$id)->get();
                    $table = Controller::getProductName($infoData[0]->cat_id);
                    $rateData = DB::table($table)->select('rebate')->where('id','=',$infoData[0]->product_id)->get();
                    $rateData = json_decode($rateData[0]->rebate);
                    end($rateData);
                    $key_last = key($rateData);
                    foreach ($rateData as $k=>$v){
                        if(intval($v->begin) <= $money && intval($v->end) > $money){
                            $rate = $v->rate;
                            continue;
                        }
                        if(intval($rateData->$key_last->end) <= $money ){
                            $rate = $rateData->$key_last->rate;
                        }
                    }
                    //money 单位 万
                    $exp_commission = $money*10000*$rate/100;
                    DB::table('information')->where('id','=',$id)->update(['money'=>$money,'comment'=>$comment,'exp_commission'=>$exp_commission,'compact_no'=>$compact_no]);
                    return response()->json(['status'=>200]);
                }catch (\Exception $e){
                    return response()->json([
                        'status' => 500,
                        'error' => [
                            'code' => '300005',
                            'message' => 'Internal server error.'
                        ]
                    ]);
                }
            }

        }else{
            return response()->json([
                'status' => 400,
                'error' => [
                    'code' => '020000',
                    'message' => 'Login required.']
            ]);

        }


    }
    public function setpTree(Request $request){
        $id = $request->input('id');
        if(empty($request->input('id'))){
            return response()->json([
                'status' => 400,
                'error' => [
                    'code' => '300006',
                    'message' => 'Videocode required.'
                ]
            ]);
        }
        if (empty($request->input('ext'))) {
            return response()->json([
                'status' => 400,
                'error' => [
                    'code' => '300007',
                    'message' => 'Videocode required.'
                ]
            ]);
        }
        if (empty($request->input('type'))) {
            return response()->json([
                'status' => 400,
                'error' => [
                    'code' => '300008',
                    'message' => 'Videocode required.'
                ]
            ]);
        }
        $type = $request->input('type');
        $userid = $this->userService->getUserId(Token::authorization());
        if (!$userid) {
            return response()->json([
                'status' => 400,
                'error' => [
                    'code' => '020000',
                    'message' => 'Login required.']
            ]);
        }
        switch ($type) {
            case 1:
                $name = 'idcard1';//证件正面
                break;
            case 2:
                $name = 'idcard2';//证件反面
                break;
            case 3:
                $name = 'bank_img';//打款银行卡照片
                break;
            case 4:
                $name = 'remit_img';//打款凭证
                break;
        }
        try{
            $qiniu = $this->utilService->qiniuGetInstance();
            $key   = sprintf('order-%s-'.$name.'.%s', $id, $request->input('ext'));
            $token = $qiniu->uploadToken(config('qiniu.qiniu_bucket'), $key, 3600, [
                // key的scope指定后, 默认上传insertOnly=0, 会自动覆盖
                'scope' => config('qiniu.qiniu_bucket').':'.$key,
            ]);
            $url = sprintf('%s/%s',config('qiniu.private_image_url'), $key);
            DB::table('information')->where('id','=',$id)->update([$name=>$url]);
            return response()->json(['status'=>200,'data'=>array('token'=>$token,'key'=>$key)]);
        }catch (\Exception $e){
            return response()->json([
                'status' => 500,
                'error' => [
                    'code' => '300009',
                    'message' => 'Internal server error.'
                ]
            ]);
        }

    }

    public function setpFour(Request $request){
            $id = $request->input('id');
            $userid = $this->userService->getUserId(Token::authorization());
            $infoData = \DB::table('information')->select("bank_img","idcard1","idcard2","remit_img","user_type")
                ->where('id','=',$id)
                ->where('userid',$userid)
                ->get();
            if($infoData[0]->user_type < 2)
            {
                if(empty($infoData[0]->idcard1)){
                    return response()->json([
                        'status' => 400,
                        'error' => [
                            'code' => '300011',
                            'message' => 'idcard front image is empty.'
                        ]
                    ]);
                }
                if(empty($infoData[0]->idcard2)){
                    return response()->json([
                        'status' => 400,
                        'error' => [
                            'code' => '300012',
                            'message' => 'idcard back image is empty.'
                        ]
                    ]);
                }
            }
            if(empty($infoData[0]->bank_img)){
                return response()->json([
                    'status' => 400,
                    'error' => [
                        'code' => '300010',
                        'message' => 'bank image is empty.'
                    ]
                ]);
            }

            if(empty($infoData[0]->remit_img)){
                return response()->json([
                    'status' => 400,
                    'error' => [
                        'code' => '300013',
                        'message' => 'remit image is empty.'
                    ]
                ]);
            }
           /* if(empty($infoData[0]->bank_img) || empty($infoData[0]->idcard1) || empty($infoData[0]->idcard2) || empty($infoData[0]->remit_img)){
                return response()->json(['status'=>200,'data'=>array('type'=>1)]);
            }else{*/
                $baseUrl = $infoData[0]->idcard1;
                // 对链接进行签名
                $url = QiNiuService::getQiNIuInstance()->privateDownloadUrl($baseUrl);
                $cli = new SwooleClient();
                $cli->connect();
                $data = [
                    'action'      => 'getIdNo',
                    'url'         => $url,
                    'id'          => $id
                ];
                $cli->send(json_encode($data));
                DB::table('information')->where('id','=',$id)->update(['status'=>1]);
                $useless = DB::table('information')->where('userid',$userid)->where('status',0)->get();
                if(!empty($useless)){
                    DB::table('information')->where('userid',$userid)->where('status',0)->delete();
                }
                return response()->json(['status'=>200]);
           /* }*/
    }

    function object_to_array($obj) {
        $obj = (array)$obj;
        foreach ($obj as $k => $v) {
            if (gettype($v) == 'resource') {
                return;
            }
            if (gettype($v) == 'object' || gettype($v) == 'array') {
                $obj[$k] = (array)$this->object_to_array($v);
            }
        }

        return $obj;
    }




}