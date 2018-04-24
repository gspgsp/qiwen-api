<?php

namespace App\Http\Controllers;

use Laravel\Lumen\Routing\Controller as BaseController;

class Controller extends BaseController
{
    /*  $data  data
        $userid  userid
        $userStatus userStatus
        $type  0:list 1:detail

    */
    public static function isLoginByProduct($data,$userid,$userStatus,$type){
        /*$productValue = array(
            10=>'资管',
            11=>'信托',
            12=>'政府债',
            13=>'上市公司融资',
            14=>'票据',
            15=>'定增',
            16=>'优质地产',
            17=>'股权',
        );*/

        if(!empty($data)){
            foreach ($data as $k=>$v){
                foreach ($v as $kk=>$vv)
                {
                    if($vv ==='' || $vv === '0' || $vv === '0000-00-00' )
                    {
                       $data[$k]->$kk= '---';
                    }
                }
            }
            if($type == 0){
                    foreach ($data as $k=>$v){
                        if($userid){
                            if($userStatus[0]->verify == 0 || $userStatus[0]->verify == 1){
                                $data[$k]->rate = "认证后可见";
                            }elseif ($userStatus[0]->verify == 2 && $data[$k]->rebate){
                                $data[$k]->rate = number_format((json_decode($data[$k]->rebate)->{'0'}->rate),2).'%';
                            }else{
                                $data[$k]->rate = "---";
                            }

                        }else{
                            $data[$k]->rate = "登录后可见";
                        }
                        if($v->catid < 15){
                            $data[$k]->totalValue = '';
                            $data[$k]->strategy = '';
                            $data[$k]->targetScale = '';
                            $data[$k]->expectedReturn = number_format($v->expectedReturn,2);
                            $data[$k]->product_type = 1;
                        }elseif ($v->catid < 17){
                            $data[$k]->targetScale = '';
                            $data[$k]->fundPeriod ='';
                            $data[$k]->expectedReturn = '';
                            $data[$k]->product_type = 2;
                        }elseif ($v->catid == 17){
                            $data[$k]->strategy ='';
                            $data[$k]->totalValue ='';
                            $data[$k]->expectedReturn = '';
                            $data[$k]->product_type = 3;
                        }
                        $data[$k]->keywords =  str_replace('，', ',', $data[$k]->keywords);
                        $data[$k]->shortName = mb_substr($data[$k]->shortName,0,15,'utf-8');
                        $data[$k]->productValue = self::getCatName($v->catid);
                    }
            }else{
                if($data[0]->introduce === '---'){
                    $data[0]->introduce = '';
                }
                if(empty($data[0]->rebate)){
                    $data[0]->rebate_arr[0]['begin'] = '---';
                    $data[0]->rebate_arr[0]['end']   = '---';
                    $data[0]->rebate_arr[0]['rate']  = '---';
                    if($data[0]->catid > 15){
                        $data[0]->rebate_arr[0]['anticipated_profits'] = '---';
                    }else{
                        $data[0]->rebate_arr[0]['anticipated_profits'] =  number_format(floatval($data[0]->expectedReturn)).'%';
                    }
                }else{
                    foreach (json_decode($data[0]->rebate) as $k=>$v){
                            $data[0]->rebate_arr[$k]['begin'] = $v->begin;
                            $data[0]->rebate_arr[$k]['end'] = $v->end;
                            if($data[0]->catid > 15){
                                $data[0]->rebate_arr[$k]['anticipated_profits'] = '---';
                            }else{
                                $data[0]->rebate_arr[$k]['anticipated_profits'] = number_format(floatval($data[0]->expectedReturn),2).'%';
                            }
                            if($userid){
                                if($userStatus[0]->verify == 2){
                                    $data[0]->rebate_arr[$k]['rate'] = number_format($v->rate,2).'%';
                                }else{
                                    $data[0]->rebate_arr[$k]['rate'] = "认证后可见";
                                }
                            }else{
                                $data[0]->rebate_arr[$k]['rate'] = "登录后可见";
                            }
                    }
                    $data[0]->shortName = mb_substr($data[0]->shortName,0,15,'utf-8');
                }
            }
        }
        return $data;
    }

    public static function getProductName($catid){
        if($catid < 15){
            $table = "trust";
        }elseif($catid < 17){
            $table = "simu";
        }elseif($catid == 17){
            $table = "pe";
        }
        return $table;
    }

    public function object_to_array($obj) {
        $obj = (array)$obj;
        foreach ($obj as $k => $v) {
            if (gettype($v) == 'resource') {
                return;
            }
            if (gettype($v) == 'object' || gettype($v) == 'array') {
                $obj[$k] = (array)object_to_array($v);
            }
        }

        return $obj;
    }

    public static function getCatName($catid){
        $productValue = array(
            10=>'集合资管',
            11=>'集合信托',
            12=>'定向融资',
            13=>'私募基金',
            14=>'海外保险',
            15=>'定增',
            16=>'优质地产',
            17=>'股权',
        );
        return $productValue[$catid];
    }

    
}
