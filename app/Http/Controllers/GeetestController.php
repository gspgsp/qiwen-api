<?php

namespace App\Http\Controllers;

use App\Services\UtilService;
use Illuminate\Http\Request;
use App\Helper\GeetestLib;
use App\Services\UserService;
use Cache;

class GeetestController extends Controller
{

    private $captcha_id;
    private $private_key;
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(UserService $userService, UtilService $utilService)
    {
        //初始化
        $this->userService = $userService;
        $this->utilService = $utilService;
        $this->captcha_id = config('geetest.captcha_id');
        $this->private_key = config('geetest.private_key');
    }

    /**
     * Geetest初始化
     */
    public function start(Request $request)
    {
        $gtSdk = new GeetestLib($this->captcha_id, $this->private_key);

        //获取user_id
        if ($request->has('user_id')) {
            $user_id = $request->input('user_id');
            Cache::forever($user_id.":userid", $user_id);
        } else {
            $mobile = $request->input('mobile');
            $user_id = generateUserName($mobile);
            Cache::forever($mobile.":userid", $user_id);
        }

        $data = array(
            "user_id" => $user_id, # 网站用户id
            "client_type" => "native", #web:电脑上的浏览器；h5:手机上的浏览器，包括移动应用内完全内置的web_view；native：通过原生SDK植入APP应用的方式
            "ip_address" => $request->getClientIp() # 请在此处传输用户请求验证时所携带的IP
        );

        $status = $gtSdk->pre_process($data, 1);
        Cache::forever($user_id.":gtserver", $status);
        Cache::forever($user_id.":gtdata", json_encode($data));
        echo $gtSdk->get_response_str();
    }

    /**
     * Geetest二次校验
     */
    public function verify(Request $request)
    {
        if (!$request->has('user_id') && !$request->has('mobile')) {
            return response()->json([
                'status' => 400,
                'error' => [
                    'code' => '030000',
                    'message' => '缺少用户信息参数'
                ]
            ], env('CLIENT_ERROR_CODE', 400));
        }

        if (!$request->has('geetest_challenge') || !$request->has('geetest_validate') || !$request->has('geetest_seccode')) {
            return response()->json([
                'status' => 400,
                'error' => [
                    'code' => '030000',
                    'message' => '缺少Geetest参数信息'
                ]
            ], env('CLIENT_ERROR_CODE', 400));
        }

        //获取user_id
        if ($request->has('user_id')) {
            $user_id = $request->input('user_id');
            $user_id = Cache::get($user_id.":userid");
            $mobile = '';
            $is_login = true;
        } else {
            $mobile = $request->input('mobile');
            $user_id = Cache::get($mobile.":userid");
            $is_login = false;
        }

        //获取geetest参数信息
        $geetest_challenge = $request->input('geetest_challenge');
        $geetest_validate = $request->input('geetest_validate');
        $geetest_seccode = $request->input('geetest_seccode');

        $data = array(
            "user_id" => $user_id, # 网站用户id
            "client_type" => "native", #web:电脑上的浏览器；h5:手机上的浏览器，包括移动应用内完全内置的web_view；native：通过原生SDK植入APP应用的方式
            "ip_address" => $request->getClientIp() # 请在此处传输用户请求验证时所携带的IP
        );

        $gtSdk = new GeetestLib($this->captcha_id, $this->private_key);
        if (Cache::get($user_id.":gtserver") == 1) {   //服务器正常
            $data = json_decode(Cache::get($user_id.":gtdata"), true);
            $result = $gtSdk->success_validate($geetest_challenge, $geetest_validate, $geetest_seccode, $data);
            if ($result) {
                $token = $this->__createToken($user_id, $mobile, $is_login);
                return response()->json([
                    'status' => 200,
                    'data' => [
                        'token' => $token
                    ]
                ]);
            } else{
                return response()->json([
                    'status' => 400,
                    'error' => [
                        'code' => '030202',
                        'message' => '验证失败'
                    ]
                ], env('CLIENT_ERROR_CODE', 400));
            }
        }else{  //服务器宕机,走failback模式
            if ($gtSdk->fail_validate($geetest_challenge, $geetest_validate, $geetest_seccode)) {
                $token = $this->__createToken($user_id, $mobile, $is_login);
                return response()->json([
                    'status' => 200,
                    'data' => [
                        'token' => $token
                    ]
                ]);
            }else{
                return response()->json([
                    'status' => 400,
                    'error' => [
                        'code' => '030202',
                        'message' => '验证失败'
                    ]
                ], env('CLIENT_ERROR_CODE', 400));
            }
        }
    }

    /**
     * 生成Geetest验证通过后的token
     * @param $user_id
     * @param $mobile
     * @param $is_login
     * @return string
     */
    private function __createToken($user_id, $mobile, $is_login)
    {
        $salt = $this->utilService->randString(6);
        $token = $this->userService->generatePassword($user_id, $salt);
        $key = $is_login ? $user_id : $mobile;
        Cache::put('geetest_token:'.$key, $token, env('SMS_TTL', 5));
        return $token;
    }
}