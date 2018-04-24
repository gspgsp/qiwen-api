<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Services\UserService;
use App\Services\UtilService;
use Illuminate\Http\Request;
use App\Helper\Token;
use App\Services\QiNiuService;
use Cache;
use Log;

class UserController extends Controller {

    public static $PAGE_SIZE = 10;

    public function __construct(UserService $userService, UtilService $utilService, QiNiuService $qiNiuService) {
        $this->userService = $userService;
        $this->utilService = $utilService;
        $this->qiNiuService = $qiNiuService;
    }

    /**
     * 判断是否存在用户
     * @param Request $request
     */
    public function is_exist_user(Request $request){
        if(empty($request->input('telephone'))){
            return response()->json([
                'status' => 400,
                'error' => [
                    'code' => '030000',
                    'message' => '参数错误.'
                ]
            ], env('CLIENT_ERROR_CODE', 400));
        }
        $userid = \DB::table('member')->where('mobile', $request->input('telephone'))->value('userid');
        return response()->json([
            'status' => 200,
            'data' => [
                'is_exist' => $userid > 0 ? 1 : 0,
            ]
        ]);
    }

    /**
     * 获取用户手机联系人
     */
    public function get_contacts(Request $request){
        $user_id = $this->userService->getUserId(Token::authorization());
        if($user_id){
            $data = addslashes(htmlspecialchars(trim($request->input('data'))));
            $info = array(
               'user_id' => $user_id,
               'data' => $data,
            );
            $res = \DB::table('contacts_encrypt')->where("user_id",$user_id)->first();
            if($res){
                $info['update_time'] = time();
                \DB::table('contacts_encrypt')->where("user_id",$user_id)->update($info);
            }else{
                $info['input_time'] = time();
                \DB::table('contacts_encrypt')->insertGetId($info);
            }
            Log::debug('contacts_encrypt', ['data' => $data,'info' => $info]);
            return response()->json([
                'status' => 200,
                'data' => [
                    'message' => '保存成功!'
                ]
            ]);
        }else{
            return response()->json([
                'status' => 400,
                'error' => [
                    'code' => '020000',
                    'message' => '用户未登录.'
                ]
            ], env('CLIENT_ERROR_CODE', 400));
        }

    }
    /**
     * 获取用户个人信息-我的
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function userInfo(){
        $uid = Token::authorization();
        if($uid){
            return response()->json([
                'status' => 200,
                'data' => $this->userService->getUserInfo($uid),
            ]);
        }else{
            return response()->json([
                'status' => 400,
                'error' => [
                    'code' => '020000',
                    'message' => '用户未登录.'
                ]
            ], env('CLIENT_ERROR_CODE', 400));
        }
    }

    /**
     * 返佣记录
     * @param Request $request
     */
    public function commision_log(Request $request){
        $uid = Token::authorization();
        $user_id = $this->userService->getUserId($uid);
        $page = empty($request->input('page')) ? 1 : $request->input('page');
        $page_size = static::$PAGE_SIZE;
        $is_last_page = 0;
        $count = \DB::table('information')
            ->join('category',function ($join) use($user_id){
                $join->on('information.cat_id','=','category.catid')
                    ->where('information.userid','=',$user_id)
                    ->where('information.return_status','=',3);
            })->count();
        $req_count = ($page + 1) * $page_size;
        if($count == 0 || $count < $req_count){
            $is_last_page = 1;
        }
        $skip = ($page - 1) * $page_size;
        $data = \DB::table('information')
            ->join('category',function ($join) use($user_id){
                $join->on('information.cat_id','=','category.catid')
                    ->where('information.userid','=',$user_id)
                    ->where('information.return_status','=',3);
            })
            ->orderBy('information.end_time','desc')
            ->skip($skip)
            ->take($page_size)
            ->select('information.id','information.product','information.return_status','information.actual_commission','information.end_time','category.catname')
            ->get();
        foreach ($data as &$v){
            $v = get_object_vars($v);
            $v['end_time'] = date('Y-m-d H:i:m',$v['end_time']);
            unset($v);
        }
        return response()->json([
            'status' => 200,
            'data' => $data,
            'is_last_page' => $is_last_page,
        ]);
    }
    /**
     * 获取提现记录
     * @param Request $request
     */
    public function cash_log(Request $request){
        $uid = Token::authorization();
        $user_id = $this->userService->getUserId($uid);
        $page = empty($request->input('page')) ? 1 : $request->input('page');
        $page_size = static::$PAGE_SIZE;
        $is_last_page = 0;
        $count = \DB::table('member_carry')
            ->join('bank',function ($join){
                $join->on('bank.id','=','member_carry.bank_id');
            })
            ->where('user_id',$user_id)
            ->count();
        $req_count = ($page + 1) * $page_size;
        if($count == 0 || $count < $req_count){
            $is_last_page = 1;
        }
        $skip = ($page - 1) * $page_size;
        $obj = \DB::table('member_carry')
                ->join('bank',function ($join){
                    $join->on('bank.id','=','member_carry.bank_id');
                })
                ->where('user_id',$user_id)
                ->orderBy('input_time','desc')
                ->skip($skip)
                ->take($page_size)
                ->select('member_carry.money','member_carry.bankcard','member_carry.status','member_carry.input_time','member_carry.update_time','member_carry.msg','bank.name')
                ->get();
        if(!empty($obj)){
            foreach ($obj as &$v){
                $v = get_object_vars($v);
                $v['input_time'] = date('Y-m-d H:i:m',$v['input_time']);
                $v['update_time'] = date('Y-m-d H:i:m',$v['update_time']);
                if($v['msg'] == NULL){
                    $v['msg'] = '';
                }
                if(in_array($v['status'],[0,1,3])){
                    $bank_info = $this->userService->getUserStatus($uid,2);
                    $v['bankcard'] = empty($bank_info) ? '' : $bank_info['bank_card_num'];
                    $v['bank_card_icon'] = empty($bank_info) ? '' : $bank_info['bank_card_icon'];
                }
                unset($v);
            }
        }
        return response()->json([
            'status' => 200,
            'data' => $obj,
            'is_last_page' => $is_last_page,
        ]);
    }

    /**
     * 提现
     * @param Request $request
     */
    public function cash_apply(Request $request){
        $user_id = $this->userService->getUserId(Token::authorization());
        $u_info = \DB::table('member')->where('userid',$user_id)->select('amount','p_password','p_encrypt')->first();
        if(empty($u_info->p_password)){
            return response()->json([
                'status' => 400,
                'error' => [
                    'code' => '020001',
                    'message' => '请先设置提现密码'
                ]
            ], env('CLIENT_ERROR_CODE', 400));
        }

        $m_bank = \DB::table('member_bank')->where(function ($query) use($user_id){
            $query->where('user_id','=',$user_id);
        })->select('id','bank_id','bankcard')->first();
        if(empty($m_bank)){
            return response()->json([
                'status' => 400,
                'error' => [
                    'code' => '020002',
                    'message' => '请先绑定银行卡'
                ]
            ], env('CLIENT_ERROR_CODE', 400));
        }

        if(empty($request->input('amount')) || empty($request->input('p_password'))){
            return response()->json([
                'status' => 400,
                'error' => [
                    'code' => '030000',
                    'message' => '参数错误.'
                ]
            ], env('CLIENT_ERROR_CODE', 400));
        }

        $amount = floatval(str_replace(',','',$request->input('amount')));
        $c_password = $this->userService->generatePassword($request->input('p_password'), $u_info->p_encrypt);
        Log::debug('cash_password', ['cash_password' => $c_password, 'cash_input_password'=>$request->input('p_password'), 'p_encrypt'=>$u_info->p_encrypt]);
        if($u_info->p_password != $c_password){
            return response()->json([
                'status' => 400,
                'error' => [
                    'code' => '020004',
                    'message' => '提现密码不正确'
                ]
            ], env('CLIENT_ERROR_CODE', 400));
        }
        $u_amount = floatval($u_info->amount);
        if($amount > $u_amount){
            return response()->json([
                'status' => 400,
                'error' => [
                    'code' => '020005',
                    'message' => '余额不足'
                ]
            ], env('CLIENT_ERROR_CODE', 400));
        }
        if($amount <= 0){
            return response()->json([
                'status' => 400,
                'error' => [
                    'code' => '020006',
                    'message' => '提现金额必须大于0'
                ]
            ], env('CLIENT_ERROR_CODE', 400));
        }

        $account_money = floatval($u_amount - $amount);

        $member = array(
            'amount' => $account_money,
            'update_time' => time(),
        );
        $cache = array(
            'user_id' => $user_id,
            'money' => $amount,
            'bank_id' => $m_bank->bank_id,
            'bankcard' => $m_bank->bankcard,
            'input_time' => time(),
            'status' => 0,
        );
        $log = array(
            'user_id' => $user_id,
            'money' => $amount,
            'account_money' =>  $account_money,
            'memo' => '用户提现',
            'input_time' =>time(),
        );

        \DB::beginTransaction();
        try{
            \DB::table('member')->where('userid',$user_id)->update($member);
            \DB::table('member_carry')->insert($cache);
            \DB::table('member_carry_log')->insert($log);
            \DB::commit();
        } catch (\Illuminate\Database\QueryException $ex){
            \DB::rollback();
            return response()->json([
                'status' => 400,
                'error' => [
                    'code' => '020007',
                    'message' => '申请提现失败'
                ]
            ], env('CLIENT_ERROR_CODE', 400));
        }
        return response()->json([
            'status' => 200,
            'data' => [
                'message' => '申请提现成功'
            ]
        ]);
    }

    /**
     * 修改用户名
     * @param Request $request
     */
    public function set_nickname(Request $request){
        $uid = Token::authorization();
        if(empty($request->input('nick_name'))){
            return response()->json([
                'status' => 400,
                'error' => [
                    'code' => '030000',
                    'message' => '参数错误.'
                ]
            ], env('CLIENT_ERROR_CODE', 400));
        }
        $num = \DB::table('member')->where('mobile',$uid)->update(['username'=>$request->input('nick_name'),'update_time'=>time()]);
        if($num){
            return response()->json([
                'status' => 200,
                'data' => [
                    'message' => '用户名设置成功'
                ]
            ]);
        }else{
            return response()->json([
                'status' => 400,
                'error' => [
                    'code' => '020009',
                    'message' => '用户名设置/修改失败',
                ]
            ], env('CLIENT_ERROR_CODE', 400));
        }

    }

    /**
     * 设置提现密码 修改登录密码
     * @param Request $request
     */
    public function set_password(Request $request){
        $confirm_password = $request->input('confirm_password');
        $token = $request->input('token');
//        $codes = $request->input('codes');
        $telephone = Token::authorization();
        /*$sessionId = $this->userService->getSessionId($telephone);
        $verificationCodes = Redis::get($sessionId.'|vcodes');
        if (empty($verificationCodes) || $verificationCodes != $codes) {
            return response()->json([
                'status' => 400,
                'error' => [
                    'code' => '020010',
                    'message' => 'Invalid verification codes.'
                ]
            ], env('CLIENT_ERROR_CODE', 400));
        }*/
        $verificationToken = Cache::get($this->userService->getSessionId($telephone).'|vtoken');
        if (empty($verificationToken) || $verificationToken != $token) {
            return response()->json([
                'status' => 400,
                'error' => [
                    'code' => '020011',
                    'message' => 'token无效.'
                ]
            ], env('CLIENT_ERROR_CODE', 400));
        }
        $password = $this->userService->decodePassword($request->input('password'));

        if ($this->userService->isBadPassword($password)) {
            return response()->json([
                'status' => 400,
                'error' => [
                    'code' => '020012',
                    'message' => '密码应该在6-20位，由字母或数字组成.'
                ]
            ], env('CLIENT_ERROR_CODE', 400));
        }
        if($request->input('type') == 1){//提现
            $salt = $this->utilService->randString(6);
            $data = [
                'p_password' => $this->userService->generatePassword($request->input('password'), $salt),
                'p_encrypt'  => $salt,
                'update_time' => time()
            ];
            Log::debug('cash_data', ['cash_data' => $data]);
        }else if($request->input('type') == 2){//修改
            $confirm_password = $this->userService->decodePassword($confirm_password);
            if ( $password != $confirm_password ) {
                return response()->json([
                    'status' => 400,
                    'error' => [
                        'code' => '020013',
                        'message' => '确认密码不正确.'
                    ]
                ], env('CLIENT_ERROR_CODE', 400));
            }
            $salt = $this->utilService->randString(6);
            $data = [
                'password' => $this->userService->generatePassword($password, $salt),
                'encrypt'  => $salt,
                'update_time' => time()
            ];
        }
        $num = \DB::table('member')->where('mobile',$telephone)->update($data);
        if($num){
                return response()->json([
                    'status' => 200,
                    'data' => [
                        'message' => '手机号设置/修改成功'
                    ]
                ]);
        }else{
            return response()->json([
                'status' => 400,
                'error' => [
                    'code' => '020014',
                    'message' => '手机号设置/修改失败',
                ]
            ], env('CLIENT_ERROR_CODE', 400));
        }
    }

    /**
     * 验证银行卡
     * @param Request $request
     */
    public function bank_verify(Request $request){
        $user_id = $this->userService->getUserId(Token::authorization());
        $files = \DB::table('member_credit_file')->select('id','id_no')->where('user_id',$user_id)->first();
        if(!$files){
            return response()->json([
                'status' => 400,
                'error' => [
                    'code' => '020015',
                    'message' => '请先进行实名认证',
                ]
            ], env('CLIENT_ERROR_CODE', 400));
        }

        if(empty($request->input('real_name')) || empty($request->input('bank_card'))){
            return response()->json([
                'status' => 400,
                'error' => [
                    'code' => '030000',
                    'message' => '参数错误.'
                ]
            ], env('CLIENT_ERROR_CODE', 400));
        }

        if(\DB::table('member_bank')->where('user_id',$user_id)->value('id')){
            return response()->json([
                'status' => 400,
                'error' => [
                    'code' => '020017',
                    'message' => '该用户已绑定银行卡.'
                ]
            ], env('CLIENT_ERROR_CODE', 400));
        }
        $res = $this->userService->getBankInfo($request->input('bank_card'));
        if($res['response_code'] == 1){
            $site_url = getCurrentDomain();
            $data = array(
                'bankcard' => $request->input('bank_card'),
                'real_name' => $request->input('real_name'),
                'bank_name' => $res['bank_name'],
                'bank_logo' => $site_url."/uploads/bank/".$res['bank'].".png",
            );
            return response()->json([
                'status' => 200,
                'data' => $data
            ]);
        }else{
            return response()->json([
                'status' => 400,
                'error' => [
                    'code' => '020018',
                    'message' => $res['show_err']
                ]
            ], env('CLIENT_ERROR_CODE', 400));
        }
    }

    /**
     * 添加银行卡
     * @param Request $request
     */
    public function bank_add(Request $request){
        $uid = Token::authorization();
        $user_id = $this->userService->getUserId($uid);
        //验证 验证码
        $res = $this->verifyCommon($request, '0');
        $res = json_decode($res->getContent(),true);
        if($res['status'] == 200){
            //先验证用户是否绑卡
            $id = \DB::table('member_bank')->where('user_id',$user_id)->value('id');
            if($id){
                return response()->json([
                    'status' => 400,
                    'error' => [
                        'code' => '030000',
                        'message' => '用户已经绑过一张卡.'
                    ]
                ], env('CLIENT_ERROR_CODE', 400));
            }
            //魔蝎验证银行卡
            if (empty($request->input('real_name')) || empty($request->input('bank_card'))) {
                return response()->json([
                    'status' => 400,
                    'error' => [
                        'code' => '030000',
                        'message' => '参数错误.'
                    ]
                ], env('CLIENT_ERROR_CODE', 400));
            }
            $files = \DB::table('member_credit_file')->select('id','id_no')->where('user_id',$user_id)->first();
            $api = config('moxie.moxie_domain')."v1/bankCardAuth";
            $params = [
                'name'   => $request->input('real_name'),
                'idCard' => $files->id_no,
                'acctNo' => $request->input('bank_card'),
                'mobile' => $request->input('telephone'),
                'type'   => "cardbin",
            ];
            $header = [
                'Authorization:token ' . config('moxie.moxie_token')
            ];
            $response = curl_request($api, 'GET', $params, $header, "json");
            Log::debug('response_bank_card', ['response_bank_card' => $response]);
            if($response['success'] == false || ($response['success'] == 'true' && $response['data']['code'] !='0')){
                return response()->json([
                    'status' => 400,
                    'error' => [
                        'code' => '020017',
                        'message' => '银行卡相关信息不对，请重新输入.'
                    ]
                ], env('CLIENT_ERROR_CODE', 400));
            }
            //
            //添加银行卡
            $res = $this->userService->getBankInfo($request->input('bank_card'));
            $bank_id = \DB::table('bank')->where('code',$res['bank'])->value('id');
            if($res['response_code'] == 1){
                $data = array(
                    'user_id' => $user_id,
                    'bank_id' => $bank_id,
                    'bankcard' => $request->input('bank_card'),
                    'real_name' => $request->input('real_name'),
                    'region_lv1'=>0,
                    'region_lv2'=>0,
                    'region_lv3'=>0,
                    'region_lv4'=>0,
                    'bankzone' => $res['bank_name'],
                    'code' => $res['bank'],
                );
                \DB::table('member_bank')->insert($data);
                return response()->json([
                    'status' => 200,
                    'data' => $this->userService->getUserInfo($uid),
                ]);
            }else{
                return response()->json([
                    'status' => 400,
                    'error' => [
                        'code' => '020020',
                        'message' => $res['show_err']
                    ]
                ], env('CLIENT_ERROR_CODE', 400));
            }
        }else{
            return response()->json([
                'status' => $res['status'],
                'error' => [
                    'code' => $res['error']['code'],
                    'message' => $res['error']['message']
                ]
            ], env('CLIENT_ERROR_CODE', 400));
        }

    }

    /**
     * 理财师认证 认证 和 修改
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function verifyFinancialer(Request $request){
        $uid = Token::authorization();
        $u_info = \DB::table('member')->where('mobile',$uid)->select('userid','verify')->first();
        //判一下该身份证有木有被用过
        $id = \DB::table('member_credit_file')->whereExists(function ($query) use($request, $u_info){
            $query->select(\DB::raw(1))
                ->from('member_credit_file')
                ->whereRaw("id_no = '".$request->input('id_no')."'"." and user_id != ".$u_info->userid);
        })->value('id');
        if($id){
            return response()->json([
                'status' => 400,
                'error' => [
                    'code' => '030000',
                    'message' => '该身份证已存在.'
                ]
            ], env('CLIENT_ERROR_CODE', 400));
        }
        //
        $file = \DB::table('member_credit_file')->where('user_id',$u_info->userid)->value('file');
        $id = $res = 0;
        if($u_info->verify == 0){
            if(empty($request->input('real_name')) || empty($request->input('id_no')) || empty($request->input('work_company'))){
                return response()->json([
                    'status' => 400,
                    'error' => [
                        'code' => '030000',
                        'message' => '参数错误.'
                    ]
                ], env('CLIENT_ERROR_CODE', 400));
            }
            //魔蝎认证
            $api = config('moxie.moxie_domain')."v2/idCardAuth";
            $params = [
                'name'   => $request->input('real_name'),
                'idCard' => $request->input('id_no'),
                'type'   => "noPhoto",
            ];
            $header = [
                'Authorization:token ' . config('moxie.moxie_token')
            ];
            $response = curl_request($api, 'GET', $params, $header, "json");
            Log::debug('response_verify', ['response' => $response]);
            if ( $response['success'] == 'true' && $response['data']['code'] == '0') {
                if(empty($file)){
                    $data = array(
                        'user_id' => $u_info->userid,
                        'real_name' => $request->input('real_name'),
                        'id_no' => $request->input('id_no'),
                        'work_company' => $request->input('work_company'),
                        'input_time' => time(),
                        'status' => 1,
                        'passed_time' => time(),
                        'passed' => 1,
                    );
                    $id = \DB::table('member_credit_file')->insertGetID($data);
                }else{
                    $data = array(
                        'real_name' => $request->input('real_name'),
                        'id_no' => $request->input('id_no'),
                        'work_company' => $request->input('work_company'),
                        'update_time' => time(),
                        'status' => 1,
                        'passed_time' => time(),
                        'passed' => 1,
                    );
                    $res = \DB::table('member_credit_file')->where('user_id',$u_info->userid)->update($data);
                }
                if($id || $res){
                    $user_info = array(
                        'verify' => 2,
                        'update_time' => time(),
                    );
                    \DB::table('member')->where('userid',$u_info->userid)->update($user_info);
                    return response()->json([
                        'status' => 200,
                        'data' => $this->userService->getUserInfo($uid),
                    ]);
                }else{
                    return response()->json([
                        'status' => 400,
                        'error' => [
                            'code' => '020022',
                            'message' => '数据操作失败.'
                        ]
                    ], env('CLIENT_ERROR_CODE', 400));
                }
            }else{
                return response()->json([
                    'status' => 400,
                    'error' => [
                        'code' => '030001',
                        'message' => '请重新填写认证信息.'
                    ]
                ], env('CLIENT_ERROR_CODE', 400));
            }
            //
        }else if($u_info->verify == 2){
            if(empty($request->input('work_company'))){
                return response()->json([
                    'status' => 400,
                    'error' => [
                        'code' => '030000',
                        'message' => '参数错误.'
                    ]
                ], env('CLIENT_ERROR_CODE', 400));
            }
            $data = array(
                'work_company' => $request->input('work_company'),
                'update_time' => time(),
            );
            \DB::table('member_credit_file')->where('user_id',$u_info->userid)->update($data);
            return response()->json([
                'status' => 200,
                'data' => $this->userService->getUserInfo($uid),
            ]);
        }
    }

    /**
     * 上传身份证
     * @param Request $request
     */
    public function upload_card(Request $request){
        $uid = Token::authorization();
        $user_id = $this->userService->getUserId($uid);
        $up_type = $request->input('up_type');// 1 file_f , 2 file_b
        $ext = $request->input('ext');//app
        $url_img = $request->input('url_img');//h5
        $user_credit = \DB::table('member_credit_file')->where('user_id',$user_id)->first();

        if($ext){
            $key = sprintf('user-%s-photo-%s.%s',$user_id, $up_type, $ext);
            $token = $this->qiNiuService->getQiNIuInstance()->uploadToken(config('qiniu.qiniu_bucket'),$key, 3600, [
                // key的scope指定后, 默认上传insertOnly=0, 会自动覆盖
                'scope' => config('qiniu.qiniu_bucket').':'.$key,
            ]);
            $url_img = sprintf('%s/%s', config('qiniu.private_image_url'), $key);
            $data['key'] = $key;
            $data['token'] = $token;
        }
        if(empty($user_credit)){
            $credit['user_id'] = $user_id;
            $credit['status'] = 0;
            $credit['input_time'] = time();
            $credit['passed'] = 0;
            $credit['passed_time'] = 0;
            $temp[$up_type] = $url_img;
            $credit['file'] = serialize($temp);
            if($id = \DB::table('member_credit_file')->insertGetId($credit)){
                $status = 200;
                $data['message'] = '上传成功';
            }
        }else{
            $user_credit = get_object_vars($user_credit);
            $file = empty($user_credit['file']) ? array() : unserialize($user_credit['file']);
            if(array_key_exists($up_type,$file)){
                unset($file[$up_type]);
                $new_file = array_merge($file,array($up_type=>$url_img));
                $credit['file'] = serialize($new_file);
                $credit['update_time'] = time();
                \DB::table('member_credit_file')->where('user_id',$user_id)->update($credit);
                $status = 200;
                $data['message'] = '更改成功';
            }else{
                $new_file = $file + array($up_type=>$url_img);
                $credit['file'] = serialize($new_file);
                $credit['update_time'] = time();
                \DB::table('member_credit_file')->where('user_id',$user_id)->update($credit);
                $status = 200;
                $data['message'] = '上传成功';
            }
        }
        return response()->json([
            'status' => $status,
            'data' => $data,
        ]);
    }

    /**
     * 获取理财师认证信息
     */
    public function getCreditInfo(){
        $uid = Token::authorization();
        $u_info = \DB::table('member_credit_file')->where('user_id',$this->userService->getUserId($uid))->first();
        $file = empty($u_info->file) ? array() : unserialize($u_info->file);
        $data = array(
            'real_name' => $u_info->real_name,
            'id_no' => $u_info->id_no,
            'work_company' => $u_info->work_company,
            'has_file' => count($file) == 0 ? 0 : 1, //0 未上传 1 已上传
            'file_f' => !empty($file[1]) ? QiNiuService::getQiNIuInstance()->privateDownloadUrl($file[1]) : '',
            'file_b' => !empty($file[2]) ? QiNiuService::getQiNIuInstance()->privateDownloadUrl($file[2]) : '',
        );
        return response()->json([
            'status' => 200,
            'data' => $data,
        ]);
    }

    /**获取上传token img_url h5
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function upload_token(){
        $token = $this->qiNiuService->getQiNIuInstance()->uploadToken(config('qiniu.qiniu_bucket'));
        $data = array(
            'token' => $token,
            'private_image_url' => config('qiniu.private_image_url'),
        );
        return response()->json([
            'status' => 200,
            'data' => $data,
        ]);
    }

    /**
     * 用户头像上传
     * @param Request $request
     */
    public function upload_thumb(Request $request){
        $uid = Token::authorization();
        $user_id = $this->userService->getUserId($uid);
        if($request->input('ext') && empty($request->input('url_img'))){
            $key = sprintf('user-%s-img.%s', $user_id, $request->input('ext'));
            $token = $this->qiNiuService->getQiNIuInstance()->uploadToken(config('qiniu.qiniu_bucket'),$key, 3600, [
                // key的scope指定后, 默认上传insertOnly=0, 会自动覆盖
                'scope' => config('qiniu.qiniu_bucket').':'.$key,
            ]);
            $url = sprintf('%s/%s', config('qiniu.private_image_url'), $key);
            $data = array(
                'thumb' => $url,
                'update_time' => time(),
            );

            $res = \DB::table('member')->where('userid',$user_id)->update($data);
            if($res){
                return response()->json([
                    'status' => 200,
                    'data' => [
                        'message' => '上传成功',
                        'key' => $key,
                        'token' => $token,
//                        'user_info' => $this->userService->getUserInfo($uid),
                    ]
                ]);
            }else{
                return response()->json([
                    'status' => 400,
                    'error' => [
                        'code' => '020024',
                        'message' => '上传失败'
                    ]
                ], env('CLIENT_ERROR_CODE', 400));
            }
        }else if($request->input('url_img')){
            $data = array(
                'thumb' => $request->input('url_img'),
                'update_time' => time(),
            );
            $res = \DB::table('member')->where('userid',$user_id)->update($data);
            if($res){
                return response()->json([
                    'status' => 200,
                    'data' => [
                        'message' => '上传成功',
                        'user_img' => $this->qiNiuService->getQiNIuInstance()->privateDownloadUrl($request->input('url_img')),
                    ]
                ]);
            }else{
                return response()->json([
                    'status' => 400,
                    'error' => [
                        'code' => '020025',
                        'message' => '上传失败'
                    ]
                ], env('CLIENT_ERROR_CODE', 400));
            }
        }
    }

    /**
     * 验证 验证码
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function verifyCode(Request $request) {
        return $this->verifyCommon($request, '0');
    }

    /**
     * 验证 验证码
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function verifySmsCode(Request $request) {
        //提現 和 修改登錄密碼 此时用户一定是登录的才可以 所以需要header请求头
        $telephone = Token::authorization();
        return $this->verifyCommon($request, $telephone);
    }

    /**
     * 验证码公共方法
     * @param Request $request
     * @param $telephone string 是否登录手机号
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function verifyCommon(Request $request, $telephone = '0'){
        if(!$telephone){
            if(empty($request->input('telephone'))){
                return response()->json([
                    'status' => 400,
                    'error' => [
                        'code' => '030000',
                        'message' => '参数错误.'
                    ]
                ], env('CLIENT_ERROR_CODE', 400));
            }
            $telephone = $request->input('telephone');
        }
        if (empty($request->input('token')) || empty($request->input('codes'))) {
            return response()->json([
                'status' => 400,
                'error' => [
                    'code' => '030000',
                    'message' => '参数错误.'
                ]
            ], env('CLIENT_ERROR_CODE', 400));
        }
        $sessionId = $this->userService->getSessionId($telephone);
        $verificationCodes = Cache::get($sessionId.'|vcodes');
        Log::debug('verificationCodes', ['sessionId'=>$sessionId,'verificationCodes' => $verificationCodes, 'codes'=>$request->input('codes')]);
        if (empty($verificationCodes) || $verificationCodes != $request->input('codes')) {
            return response()->json([
                'status' => 400,
                'error' => [
                    'code' => '020027',
                    'message' => '验证码无效.'
                ]
            ], env('CLIENT_ERROR_CODE', 400));
        }
        $verificationToken = Cache::get($this->userService->getSessionId($telephone).'|vtoken');
        if (empty($verificationToken) || $verificationToken != $request->input('token')) {
            return response()->json([
                'status' => 400,
                'error' => [
                    'code' => '020028',
                    'message' => '验证码无效.'
                ]
            ], env('CLIENT_ERROR_CODE', 400));
        }
        //销毁验证码
        Cache::forget($sessionId.'|vcodes');
        //
        return response()->json([
            'status' => 200,
            'data' => [
                'token' => $request->input('token')
            ]
        ]);
    }

    /**
     * 注册
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function register(Request $request) {
        $time = time();
        //判断是否有推荐人
        if(empty($request->input('referer'))){
            if (empty($request->input('telephone')) || empty($request->input('password')) || empty($request->input('confirm_password')) || empty($request->input('token')) || empty($request->input('udid'))) {
                return response()->json([
                    'status' => 400,
                    'error' => [
                        'code' => '030000',
                        'message' => '参数错误.'
                    ]
                ], env('CLIENT_ERROR_CODE', 400));
            }

            $password = $this->userService->decodePassword($request->input('password'));

            if ($this->userService->isBadPassword($password)) {
                return response()->json([
                    'status' => 400,
                    'error' => [
                        'code' => '020030',
                        'message' => '密码应该在6-20位，由字母或数字组成.'
                    ]
                ], env('CLIENT_ERROR_CODE', 400));
            }

            $confirm_password = $this->userService->decodePassword($request->input('confirm_password'));
            if ( $password != $confirm_password ) {
                return response()->json([
                    'status' => 400,
                    'error' => [
                        'code' => '020031',
                        'message' => '确认密码不正确'
                    ]
                ], env('CLIENT_ERROR_CODE', 400));
            }

            $verificationToken = Cache::get($this->userService->getSessionId($request->input('telephone')).'|vtoken');
            if (empty($verificationToken) || $verificationToken != $request->input('token')) {
                return response()->json([
                    'status' => 400,
                    'error' => [
                        'code' => '020032',
                        'message' => 'token无效.'
                    ]
                ], env('CLIENT_ERROR_CODE', 400));
            }
            $access_token = Token::encode(['uid' => $request->input('telephone')]);
            $udid = $request->input('udid');
            $salt = $this->utilService->randString(6);
            $user = [
                'username' => generateUserName($request->input('telephone')),
                'mobile'   => $request->input('telephone'),
                'password' => $this->userService->generatePassword($password, $salt),
                'regdate'  => $time,
                'encrypt'  => $salt,
                'udid'     => $udid,
                'groupid'  => 2,
                'modelid'  => 10,
            ];
            //判断是否返回再确认
            if($userid = \DB::table('member')->where('mobile',$request->input('telephone'))->where('update_time','=',0)->value('userid')){
                \DB::table('member')->where('userid',$userid)->update($user);
                $expiresAt = Carbon::now()->addMinutes(config('token.ttl'));
                Cache::put('access_token:'.$request->input('telephone'), $access_token, $expiresAt);
                /*return response()->json([
                    'status' => 200,
                    'access_token' => $access_token,
                    'data' => $this->userService->getUserInfo($request->input('telephone')),
                ]);*/
                return response()->json([
                    'status' => 200,
                    'data' => [
                        'access_token' => $access_token,
                        'user_info' => $this->userService->getUserInfo($request->input('telephone'))
                    ]
                ]);
            }

            try {
                \DB::table('member')->insert($user);
                $expiresAt = Carbon::now()->addMinutes(config('token.ttl'));
                Cache::put('access_token:'.$request->input('telephone'), $access_token, $expiresAt);
                return response()->json([
                    'status' => 200,
                    'data' => [
                        'access_token' => $access_token,
                        'user_info' => $this->userService->getUserInfo($request->input('telephone'))
                    ]
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'status' => 500,
                    'error' => [
                        'code' => '020033',
                        'message' => 'Internal Server error.'
                    ]
                ], env('SERVER_ERROR_CODE', 500));
            }
        }else{
            /*$data['referer'] = $request->input('referer');
            $data['update_time'] = $time;
            if ($payload = Token::decode($request->input('access_token'))) {
                if (is_object($payload) && property_exists($payload, 'uid')) {
                    $num = $conn->table('member')->where('mobile',$payload->uid)->update($data);
                    if($num){
                        Redis::set('access_token:'.$request->input('telephone'), $request->input('access_token'));
                        return response()->json([
                            'status' => 200,
                            'data' => [
                                'access_token' => $request->input('access_token'),
                                'user_info' => $this->userService->getUserInfo($request->input('telephone'))
                            ]
                        ]);
                    }else{
                        return response()->json([
                            'status' => 500,
                            'error' => [
                                'code' => '020007',
                                'message' => 'Internal Server error.'
                            ]
                        ], env('SERVER_ERROR_CODE', 500));
                    }
                }
            }else{
                return response()->json([
                    'status' => 400,
                    'error' => [
                        'code' => '020008',
                        'message' => 'required token.'
                    ]
                ], env('CLIENT_ERROR_CODE', 400));
            }*/

        }
    }

    /**
     * 注册-推荐人
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function get_refer(Request $request){
        $time = time();
        $data['referer'] = $request->input('referer');
        $data['update_time'] = $time;
        if ($payload = Token::decode($request->input('access_token'))) {
            if (is_object($payload) && property_exists($payload, 'uid')) {
                $num = \DB::table('member')->where('mobile',$payload->uid)->update($data);
                if($num){
                    /*Cache::put('access_token:'.$request->input('telephone'), $request->input('access_token'));
                    return response()->json([
                        'status' => 200,
                        'access_token' => $request->input('access_token'),
                        'data' => $this->userService->getUserInfo($request->input('telephone')),
                    ]);*/
                    return response()->json([
                        'status' => 200,
                        'data' => [
                            'access_token' => $request->input('access_token'),
                            'user_info' => $this->userService->getUserInfo($payload->uid)
                        ]
                    ]);
                }else{
                    return response()->json([
                        'status' => 400,
                        'error' => [
                            'code' => '020034',
                            'message' => '更新数据失败.'
                        ]
                    ], env('CLIENT_ERROR_CODE', 400));
                }
            }
        }else{
            return response()->json([
                'status' => 400,
                'error' => [
                    'code' => '020035',
                    'message' => 'token无效.'
                ]
            ], env('CLIENT_ERROR_CODE', 400));
        }
    }

    /**
     * 登录
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function login(Request $request){
        if (empty($request->input('telephone')) || empty($request->input('password'))) {
            return response()->json([
                'status' => 400,
                'error' => [
                    'code' => '030000',
                    'message' => '参数错误.'
                ]
            ], env('CLIENT_ERROR_CODE', 400));
        }
        if(!$this->userService->isTelephone($request->input('telephone'))){
            return response()->json([
                'status' => 400,
                'error' => [
                    'code' => '030037',
                    'message' => '账号或密码错误.'
                ]
            ], env('CLIENT_ERROR_CODE', 400));
        }
        Log::debug('user_mobile', ['user_mobile' => $request->input('telephone')]);
        if($user_info = \DB::table('member')->where('mobile',$request->input('telephone'))->first()){
            if($user_info->islock == 1){
                return response()->json([
                    'status' => 400,
                    'error' => [
                        'code' => '020038',
                        'message' => '用户已经被锁定，请联系客服'
                    ]
                ], env('CLIENT_ERROR_CODE', 400));
            }
            $password = $this->userService->generatePassword($this->userService->decodePassword($request->input('password')), $user_info->encrypt);
            if($password == $user_info->password){
                $access_token = Token::encode(['uid' => $request->input('telephone')]);
                //Redis::set('access_token:'.$request->input('telephone'), $access_token);
                $expiresAt = Carbon::now()->addMinutes(config('token.ttl'));
                Cache::put('access_token:'.$request->input('telephone'), $access_token, $expiresAt);
                /*return response()->json([
                    'status' => 200,
                    'access_token' => $access_token,
                    'data' => $this->userService->getUserInfo($request->input('telephone')),
                ]);*/
                return response()->json([
                    'status' => 200,
                    'data' => [
                        'access_token' => $access_token,
                        'user_info' => $this->userService->getUserInfo($request->input('telephone'))
                    ]
                ]);
            }else{
                return response()->json([
                    'status' => 400,
                    'error' => [
                        'code' => '030039',
                        'message' => '账号或密码错误.'
                    ]
                ], env('CLIENT_ERROR_CODE', 400));
            }
        }else{
            return response()->json([
                'status' => 400,
                'error' => [
                    'code' => '020040',
                    'message' => '账号或密码错误.'
                ]
            ], env('CLIENT_ERROR_CODE', 400));
        }

    }

    /**
     * 退出登录
     * @param Request $request
     */
    public function logout(){
        $uid = Token::authorization();
        $res = Cache::forget('access_token:'.$uid);
        if($res){
            return response()->json([
                'status' => 200,
                'data' => [
                    'message' => '退出登录成功'
                ]
            ]);
        }else{
            return response()->json([
                'status' => 400,
                'error' => [
                    'code' => '020041',
                    'message' => '退出登录失败'
                ]
            ], env('CLIENT_ERROR_CODE', 400));
        }
    }

    /**
     * 忘记密码
     */
    public function resetPwd(Request $request){
        $time = time();
        if (empty($request->input('telephone')) || empty($request->input('password')) || empty($request->input('confirm_password')) || empty($request->input('token'))) {
            return response()->json([
                'status' => 400,
                'error' => [
                    'code' => '030000',
                    'message' => '参数错误.'
                ]
            ], env('CLIENT_ERROR_CODE', 400));
        }

        $password = $this->userService->decodePassword($request->input('password'));

        if ($this->userService->isBadPassword($password)) {
            return response()->json([
                'status' => 400,
                'error' => [
                    'code' => '020043',
                    'message' => '密码在6-20位，由字母或数字组成.'
                ]
            ], env('CLIENT_ERROR_CODE', 400));
        }

        $confirm_password = $this->userService->decodePassword($request->input('confirm_password'));
        if ( $password != $confirm_password ) {
            return response()->json([
                'status' => 400,
                'error' => [
                    'code' => '020044',
                    'message' => '确认密码不正确.'
                ]
            ], env('CLIENT_ERROR_CODE', 400));
        }

        $verificationToken = Cache::get($this->userService->getSessionId($request->input('telephone')).'|vtoken');
        if (empty($verificationToken) || $verificationToken != $request->input('token')) {
            return response()->json([
                'status' => 400,
                'error' => [
                    'code' => '020045',
                    'message' => 'token无效.'
                ]
            ], env('CLIENT_ERROR_CODE', 400));
        }
        $salt = $this->utilService->randString(6);
        $data = [
            'password' => $this->userService->generatePassword($password, $salt),
            'encrypt'  => $salt,
            'update_time' => $time
        ];
        $num = \DB::table('member')->where('mobile',$request->input('telephone'))->update($data);
        if($num){
            $access_token = Token::encode(['uid' => $request->input('telephone')]);
            $expiresAt = Carbon::now()->addMinutes(config('token.ttl'));
            Cache::put('access_token:'.$request->input('telephone'), $access_token, $expiresAt);
            return response()->json([
                'status' => 200,
                'data' => [
                    'access_token' => $access_token,
                    'user_info' => $this->userService->getUserInfo($request->input('telephone'))
                ]
            ]);
        }else{
            return response()->json([
                'status' => 400,
                'error' => [
                    'code' => '020046',
                    'message' => '数据更新失败.'
                ]
            ], env('CLIENT_ERROR_CODE', 400));
        }
    }

    public function myOrderList(Request $request){
        $userid = $this->userService->getUserId(Token::authorization());
        $orderStatus = intval($request->input('status'));
        if($userid){
            $pagesize = $request->input('pagesize');
            $offset = ($request->input('page')-1) * $pagesize;
            $arr =array();
            try{
                if($orderStatus == 4){
                    $infoData = \DB::table('information')->select("id","product","name","money","fundPeriod","return_status","cat_id","create_time","fundPeriod")
                                                        ->where('userid','=',$userid)
                                                        ->where('status',1)
                                                        ->OrderBy("create_time","DESC")
                                                        ->skip($offset)
                                                        ->take($pagesize)
                                                        ->get();
                    $count_order = \DB::table('information')->select(\DB::raw('count(*) as count_order'))->where('userid',$userid)->where("status",1)->get();
                    $isLastPage = ceil($count_order[0]->count_order /$pagesize ) <= $request->input('page') ? 1:0;
                }else{
                    $infoData = \DB::table('information')->select("id","product","name","money","fundPeriod","return_status","cat_id","create_time","fundPeriod")
                                                         ->where('userid','=',$userid)
                                                         ->where('return_status','=',$orderStatus)
                                                         ->where('status',1)
                                                         ->OrderBy("create_time","DESC")
                                                         ->skip($offset)
                                                         ->take($pagesize)
                                                         ->get();
                    $count_order = \DB::table('information')->select(\DB::raw('count(*) as count_order'))->where('userid',$userid)->where("status",1)->where('return_status',$orderStatus)->get();
                    $isLastPage = ceil($count_order[0]->count_order /$pagesize ) <= $request->input('page') ? 1:0;
                }

                foreach ($infoData as $k=>$v){
                    $infoData[$k]->product_type = $this->getCatName($v->cat_id);
                    switch ($v->return_status){
                        case 0:
                            $v->check_status = "待审核";
                            break;
                        case 1:
                            $v->check_status = "失败";
                            break;
                        case 2:
                            $v->check_status = "待结佣";
                            break;
                        case 3:
                            $v->check_status = "结佣成功";
                            break;
                    }
                    foreach ($v as $kk=>$vv)
                    {
                        if($vv === ''){
                            $infoData[$k]->$kk = '---';
                        }
                    }
                }
                return response()->json(['status'=>200,'data'=>$infoData,'isLastPage'=>$isLastPage]);
            }catch (\Exception $e){
                return response()->json([
                    'status' => 500,
                    'error' => [
                        'code' => '020047',
                        'message' => 'Internal server error.'
                    ]
                ]);
            }
        }else{
            return response()->json([
                'status' => 400,
                'error' => [
                    'code' => '020048',
                    'message' => 'Login required.']
            ]);
        }

    }
    public function myOrderDetail(Request $request){
        $id = $request->input('id');
        $userid = $this->userService->getUserId(Token::authorization());
        if($userid > 0){
            $info_data = \DB::table('information')->select("product","name","money","user_type","idno","remit_img","idcard1","idcard2","bank_img","checktime","end_time","create_time","cat_id","product_id","return_status","compact_no")->where('id','=',$id)->where('userid',$userid)->get();
            foreach ($info_data as $k=>$v){
                if($v->user_type == 1){
                    $v->user_type = "个人";
                }else{
                    $v->user_type = "机构";
                }
                if(empty($v->remit_img)){
                    $v->remit = "未上传";
                }else{
                    $v->remit = "已上传";
                }
                if(empty($v->idcard1) || empty($v->idcard2)){
                    $v->idcard = "未上传";
                }else{
                    $v->idcard = "已上传";
                }
                if(empty($v->bank_img)){
                    $v->bank = "未上传";
                }else{
                    $v->bank = "已上传";
                }
                if(empty($v->idno)){
                    $v->idno =  '';
                }
                switch ($v->return_status){
                    case 0:
                        $v->check_status = "待审核";
                        break;
                    case 1:
                        $v->check_status = "失败";
                        break;
                    case 2:
                        $v->check_status = "待结佣";
                        break;
                    case 3:
                        $v->check_status = "结佣成功";
                        break;
                }
                foreach ($v as $kk=>$vv)
                {
                    if($vv === ''){
                        $info_data[$k]->$kk = '---';
                    }
                }
                $info_data[$k]->cat_name = $this->getCatName($v->cat_id);
            }
            return response()->json(['status'=>200,'data'=>$info_data]);
        }else{
            return response()->json([
                'status' => 400,
                'error' => [
                    'code' => '020049',
                    'message' => 'Login required.']
            ]);
        }

    }

}
