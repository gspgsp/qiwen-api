<?php
namespace App\Services;
use Illuminate\Support\Facades\Redis;
use Cache;
use App\Services\QiNiuService;
use Log;

class UserService {

    public function getSessionId($telephone){
        return 'token:'.$telephone;
    }

    public function isTelephone($telephone){
        return preg_match('/^(?:13\d{9}|15[0|1|2|3|5|6|7|8|9]\d{8}|18\d{9}|14[5|7]\d{8}|17\d{9})$/',$telephone) == 1 ? true : false;
    }
    public  function  isIdNumber($idnumber){
        return preg_match('/^[1-9]\d{5}(19|20)\d{2}((0[1-9])|(1[0-2]))(0[1-9]|([1|2]\d)|3[0-1])\d{3}(\d|X|x)$/',$idnumber)?true:false;
    }
    public function isBadPassword($password) {
        if (preg_match('/^[0-9a-zA-Z]{6,20}$/', $password) || preg_match('/^(\d)\1+$/', $password)) {
            return false;
        }
        return true;
    }

    public function decodePassword($password){
        $password = $password[strlen($password) - 1].substr($password, 1, -1).$password[0];
        $password = strrev($password);
        $password = base64_decode($password);
        return $password;
    }

    public function generatePassword($password, $salt) {
        return md5(md5($password).$salt);
    }

    public function getUdid($access_token) {
        $key = $this->getUdidKey($access_token);
        $udid = Cache::get($key);
        if ($udid) {
            return $udid;
        }
        $rs = \DB::connection('Oauth_mysql')->select('select client_id as udid from oauth_sessions as s join oauth_access_tokens as a on s.id = a.session_id where a.id = ?', [$access_token]);
        if ($rs) {
            $udid = $rs[0]->udid;
            //Redis::setex($key, env('OAUTH_TTL', 3600), $udid);
            Cache::put($key, $udid, env('OAUTH_TTL', 60));
            return $udid;
        }
        return false;
    }

    /**
     * @deprecated
     * @return string
     */
    public function getUidKey($udid) {
        return 'udid:'.$udid.'|uid';
    }

    public function getUidKeyByUdid($udid) {
        return 'uid:*|udid:'.$udid;
    }

    public function getUdidKeyByUid($uid) {
        return 'uid:'.$uid.'|udid:*';
    }

    public function getUdidUidKey($udid, $uid) {
        return 'uid:'.$uid.'|udid:'.$udid;
    }

    public function getUdidKey($access_token){
        return 'token:'.$access_token.'|udid';
    }

    public function getUserTeacherKey($uid, $tid, $rid){
        return 'uid:'.$uid.'|tid:'.$tid.'|rid:'.$rid;
    }

    public function getUserKey($uid) {
        return 'uid:'.$uid;
    }

    public function getDeviceTokenKey($udid) {
        return 'udid:'.$udid.'|dtoken';
    }

//    public function getUserId($access_token) {
//        $keys = Redis::keys($this->getUidKeyByUdid($this->getUdid($access_token)));
//        if (empty($keys)) {
//            return false;
//        }
//        preg_match('/uid:(.*?)\|/', $keys[0], $matches);
//        return $matches[1] ? $matches[1] : false;
//    }

    public function getUserIdByUdid($udid) {
        $keys = Redis::keys($this->getUidKeyByUdid($udid));
        if (empty($keys)) {
            return false;
        }
        preg_match('/uid:(.*?)\|/', $keys[0], $matches);
        return $matches[1] ? $matches[1] : false;
    }

    public function checkPassword($origin, $salt, $password) {
        return md5(md5($origin).$salt) == $password;
    }

    public function handanKey($rid, $tid, $time){
        return 'rid:'.$rid.'|tid:'.$tid.'|time:'.$time;
    }
    
    public function sort_field($data,$sort){
        $arrSort = [];
        foreach($data AS $uniqid => $row){
            foreach($row AS $key=>$value){
                $arrSort[$key][$uniqid] = $value;
            }
        }
        if($sort['direction']){
            array_multisort($arrSort[$sort['field']], constant($sort['direction']), $data);
        }
        return $data;
    }

    /**
     * @desc 获取用户登录状态
     * 后续登录状态推荐使用此函数 避免产生过多历史遗留问题
     * @return bool
     */
    public function getLoginStatus($access_token){
        $userId = $this->getUserId($access_token);
        return $userId ? true : false;
    }

    public function checkDeviceToken($str) {
        return ($l = strlen($str)) == 44 || $l == 64 ? true : false;
    }

    /**
     * 获取用户认证状态 是否绑卡状态
     * @param $telephone
     */
    public function getUserStatus($mobile, $type = 1){
        $user_id = $this->getUserId($mobile);
        switch ($type){
            case 1:
                $id_passed = 0;
                $user_credit = \DB::table('member_credit_file')
                                    ->where(function ($query)use($user_id){
                                        $query->where('user_id','=',$user_id);
                                    })
                                    ->first();
                if($user_credit && !empty($user_credit->real_name) && !empty($user_credit->id_no) && !empty($user_credit->work_company)){
//                    $file = !empty($user_credit->file) ? unserialize($user_credit->file) : '';
                    /*if ( $user_credit->passed == 1 && $user_credit->passed_time ) {
                        $id_passed = 2; // 认证通过
                    } else if ( $user_credit->passed == 0 && $user_credit->passed_time == 0 ) {
                        $id_passed = 1; // 认证中
                    } else if ( $user_credit->passed == 2 && $user_credit->passed_time ) {
                        $id_passed = 3; // 认证不通过
                    }*/
                    if ( $user_credit->passed == 1 && $user_credit->passed_time ) {
                        $id_passed = 2; // 认证通过
                    }
                }else{
                    $id_passed = 0; // 未认证
                }
                return $id_passed;
            case 2:
                $key = "bank_info_uid:{$mobile}:type:{$type}";
                $res = Cache::get($key);
                if(empty($res)){
                    $user_bank = \DB::table('member_bank')
                        ->join('bank',function ($join) use($user_id){
                            $join->on('member_bank.bank_id','=','bank.id')
                                ->where('member_bank.user_id','=',$user_id);
                        })
                        ->select('member_bank.bank_id','member_bank.bankcard','bank.icon','bank.name')
                        ->first();
                    if($user_bank){
                        $user_bank = get_object_vars($user_bank);
                        $site_domain = getCurrentDomain();
                        $binded_card = array('bank_card_icon'=>$site_domain.$user_bank['icon'], 'bank_card_num'=>substr($user_bank['bankcard'],-4),'bank_id'=>$user_bank['bank_id'],'bank_name'=>$user_bank['name']);
                        Cache::put($key, serialize($binded_card), 1440);
                        return $binded_card;
                    }else{
                        return array('bank_card_icon'=>'', 'bank_card_num'=>'','bank_id'=>'','bank_name'=>'');;
                    }
                }
                return unserialize($res);
        }
    }

    /**
     * 获取用户id
     * @param $mobile
     * @return int
     */
    public function getUserId($mobile){
        $key = "uid:{$mobile}";
        $user_id = Cache::get($key);
        if(empty($user_id)){
            $user_id = \DB::table('member')->where('mobile',$mobile)->where('islock',0)->value('userid');
            Cache::put($key, $user_id, 1440);
        }
        return $user_id > 0 ? $user_id : 0;
    }

    /**
     * @param $mobile
     * @return array
     */
    public function getUserInfo($user_id){
        $user_info = \DB::table('users')->where('user_id',$user_id)->select("user_id","nickname","sex","head_pic")->first();
        return $user_info;
    }

    /**
     * 获取银行卡信息
     * @param $bank_card
     */
    public function getBankInfo($bankcard){
        $document_root = getDocumentRoot();
        $url = "https://ccdcapi.alipay.com/validateAndCacheCardInfo.json?_input_charset=utf-8&cardNo=".$bankcard."&cardBinCheck=true";
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_HEADER, 0 ); // 过滤HTTP头
        curl_setopt($curl,CURLOPT_RETURNTRANSFER, 1);// 显示输出结果
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);//SSL证书认证
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);//严格认证
        curl_setopt($curl, CURLOPT_CAINFO, $document_root.'/cacert.pem');//证书地址
        $responseText = json_decode(curl_exec($curl),true);

        curl_close($curl);
        if($responseText['validated']){
            switch ($responseText['cardType']){
                case 'DC':
                    $res = $this->getBankName($responseText['bank']);
                    return empty($res) ? array('response_code'=>0,'show_err'=>'未查到银行名称') : array('response_code'=>1,'bank_name'=>$res,'bank'=>$responseText['bank']);
                case 'CC':
                    return array('response_code'=>0,'show_err'=>'信用卡不可添加');
            }
        }else{
            return array('response_code'=>0,'show_err'=>'未查到银行信息');
        }
    }

    /**
     * 获取银行卡名称
     * @param $key
     */
    public function getBankName($key){
        $document_root = getDocumentRoot();
        $allbank = json_decode(file_get_contents($document_root.'/uploads/bank_name/bankname.json'),true);
        return $allbank[$key] ;
    }
    
}