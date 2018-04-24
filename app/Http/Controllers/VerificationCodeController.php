<?php
namespace App\Http\Controllers;

use App\Helper\Token;
use App\Services\Sms\SmsService;
use App\Services\UserService;
use App\Services\UtilService;
use Illuminate\Http\Request;
use Cache;

class VerificationCodeController extends Controller {

    public static $USER_REGISTER = 1;
    public static $OPEN_ACCOUNT = 2;
    public static $MODIFY_PASSWORD = 3;
    public static $ADD_BANK = 4;
    public static $SET_CACHE_PWD = 5;
    public static $MODIFY_LOGIN = 6;
    public static $PERIOD_LIMIT = 3;

    public function __construct(UserService $userService , UtilService $utilService, SmsService $smsService) {
        $this->userService = $userService;
        $this->utilService = $utilService;
        $this->smsService = $smsService;
    }

    public function get(Request $request) {
        $telephone = $request->input('telephone');
        $token = $request->input('token');
        $type = $request->input('type');
        //提現 和 修改登錄密碼 此时用户一定是登录的才可以 所以需要header请求头
        /*if($type == static::$SET_CACHE_PWD || $type == static::$MODIFY_LOGIN){
            $telephone = Token::authorization();
        }*/
        if (empty($telephone) || empty($token) || empty($type)) {
            return response()->json([
                'status' => 400,
                'error' => [
                    'code' => '030000',
                    'message' => '参数错误.'
                ]
            ], env('CLIENT_ERROR_CODE', 400));
        }

        if (!$this->userService->isTelephone($telephone)) {
            return response()->json([
                'status' => 400,
                'error' => [
                    'code' => '030001',
                    'message' => '账号或密码错误'
                ]
            ], env('CLIENT_ERROR_CODE', 400));
        }

        if(!Cache::pull('geetest_token:'.$telephone) == $token) {
            return response()->json([
                'status' => 400,
                'error' => [
                    'code' => '030201',
                    'message' => 'token无效或已经失效'
                ]
            ], env('CLIENT_ERROR_CODE', 400));
        }

        $code = $this->utilService->randNumberString(6);
        $count = Cache::get($this->userService->getSessionId($telephone.'|vcodec'));

        if (!$count) {
            //Redis::setex($this->userService->getSessionId($telephone.'|vcodec'), env('OAUTH_TTL', 3600), 1);
            Cache::put($this->userService->getSessionId($telephone.'|vcodec'), 1, env('SMS_LIMIT', 60));
        } elseif ($count < static::$PERIOD_LIMIT) {
            //Redis::incr($this->userService->getSessionId($telephone.'|vcodec'));
            Cache::increment($this->userService->getSessionId($telephone.'|vcodec'));
        } else {
            return response()->json([
                'status' => 400,
                'error' => [
                    'code' => '030102',
                    'message' => '短信发送次数已达上线.'
                ]
            ], env('CLIENT_ERROR_CODE', 400));
        }
        switch ($type) {
            case static::$USER_REGISTER:
                /*$oldRecord = \DB::table('member')->where('mobile', $telephone)->get();
                if ($oldRecord) {
                    return response()->json([
                        'status' => 400,
                        'error' => [
                            'code' => '020004',
                            'message' => '用户已存在.'
                        ]
                    ], env('CLIENT_ERROR_CODE', 400));
                }*/
                $result = $this->smsService->send(sprintf('【我是理财师】您的验证码是%s。如非本人操作，请忽略本短信', $code), $telephone);
                break;
            case static::$OPEN_ACCOUNT:
                $result = $this->smsService->send(sprintf('【我是理财师】您的验证码是%s。如非本人操作，请忽略本短信', $code), $telephone);
                break;
            case static::$MODIFY_PASSWORD:
            case static::$MODIFY_LOGIN:
                $result = $this->smsService->send(sprintf('【我是理财师】您的验证码是%s。如非本人操作，请忽略本短信', $code), $telephone);
                /*$oldRecord = \DB::table('member')->where('mobile', $telephone)->get();
                if(!empty($oldRecord)){
                    $result = $this->smsService->send(sprintf('【我是理财师】您的验证码是%s。如非本人操作，请忽略本短信', $code), $telephone);
                }else{
                    return response()->json([
                        'status' => 400,
                        'error' => [
                            'code' => '020005',
                            'message' => '用户不存在.'
                        ]
                    ], env('CLIENT_ERROR_CODE', 400));
                }*/
                break;
            case static::$ADD_BANK:
                    $result = $this->smsService->send(sprintf('【我是理财师】您的验证码是%s。如非本人操作，请忽略本短信', $code), $telephone);
                break;
            case static::$SET_CACHE_PWD:
                $result = $this->smsService->send(sprintf('【我是理财师】您的验证码是%s。如非本人操作，请忽略本短信', $code), $telephone);
                break;
            default:
                break;
        }
        if ($this->smsService->success($result)) {
            $salt = $this->utilService->randString(6);
            $token = $this->userService->generatePassword($telephone, $salt);
            //Redis::setex($this->userService->getSessionId($telephone).'|vcodes', env('OAUTH_TTL', 3600), $code);
            Cache::put($this->userService->getSessionId($telephone).'|vcodes', $code, env('SMS_TTL', 5));
            //Redis::set($this->userService->getSessionId($telephone).'|vtoken', $token);
            Cache::put($this->userService->getSessionId($telephone).'|vtoken', $token, env('SMS_TTL', 5));
            return response()->json([
                'status' => 200,
                'data' => [
                    'token' => $token,
                    'telephone' => $telephone
                ]
            ]);
        } else if ($this->smsService->beyondLimit($result)) {
            return response()->json([
                'status' => 400,
                'error' => [
                    'code' => '030002',
                    'message' => '超限.'
                ]
            ], env('CLIENT_ERROR_CODE', 400));
        } else {
            return response()->json([
                'status' => 500,
                'error' => [
                    'code' => '030003',
                    'message' => 'Sms failed.'
                ]
            ], env('SERVER_ERROR_CODE', 500));
        }

    }

    /**
     * 获取短信验证码
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function get_verify_code(Request $request) {
        //提現 和 修改登錄密碼 此时用户一定是登录的才可以 所以需要header请求头SET_CACHE_PWD MODIFY_LOGIN
        $telephone = Token::authorization();
        $token = $request->input('token');
        $type = $request->input('type');
        if (empty($token) || empty($type)) {
            return response()->json([
                'status' => 400,
                'error' => [
                    'code' => '030000',
                    'message' => '参数错误.'
                ]
            ], env('CLIENT_ERROR_CODE', 400));
        }
        $user_id = $this->userService->getUserId($telephone);
        if(!Cache::pull('geetest_token:'.$user_id) == $token) {
            return response()->json([
                'status' => 400,
                'error' => [
                    'code' => '030201',
                    'message' => 'token无效或已经失效'
                ]
            ], env('CLIENT_ERROR_CODE', 400));
        }

        $code = $this->utilService->randNumberString(6);
        $count = Cache::get($this->userService->getSessionId($telephone.'|vcodec'));

        if (!$count) {
            //Redis::setex($this->userService->getSessionId($telephone.'|vcodec'), env('OAUTH_TTL', 3600), 1);
            Cache::put($this->userService->getSessionId($telephone.'|vcodec'), 1, env('SMS_LIMIT', 60));
        } elseif ($count < static::$PERIOD_LIMIT) {
            //Redis::incr($this->userService->getSessionId($telephone.'|vcodec'));
            Cache::increment($this->userService->getSessionId($telephone.'|vcodec'));
        } else {
            return response()->json([
                'status' => 400,
                'error' => [
                    'code' => '030102',
                    'message' => '短信发送次数已达上线.'
                ]
            ], env('CLIENT_ERROR_CODE', 400));
        }
        switch ($type) {
            case static::$USER_REGISTER:
                /*$oldRecord = \DB::table('member')->where('mobile', $telephone)->get();
                if ($oldRecord) {
                    return response()->json([
                        'status' => 400,
                        'error' => [
                            'code' => '020004',
                            'message' => '用户已存在.'
                        ]
                    ], env('CLIENT_ERROR_CODE', 400));
                }*/
                $result = $this->smsService->send(sprintf('【我是理财师】您的验证码是%s。如非本人操作，请忽略本短信', $code), $telephone);
                break;
            case static::$OPEN_ACCOUNT:
                $result = $this->smsService->send(sprintf('【我是理财师】您的验证码是%s。如非本人操作，请忽略本短信', $code), $telephone);
                break;
            case static::$MODIFY_PASSWORD:
            case static::$MODIFY_LOGIN:
                $result = $this->smsService->send(sprintf('【我是理财师】您的验证码是%s。如非本人操作，请忽略本短信', $code), $telephone);
                /*$oldRecord = \DB::table('member')->where('mobile', $telephone)->get();
                if(!empty($oldRecord)){
                    $result = $this->smsService->send(sprintf('【我是理财师】您的验证码是%s。如非本人操作，请忽略本短信', $code), $telephone);
                }else{
                    return response()->json([
                        'status' => 400,
                        'error' => [
                            'code' => '020005',
                            'message' => '用户不存在'
                        ]
                    ], env('CLIENT_ERROR_CODE', 400));
                }*/
                break;
            case static::$ADD_BANK:
                $result = $this->smsService->send(sprintf('【我是理财师】您的验证码是%s。如非本人操作，请忽略本短信', $code), $telephone);
                break;
            case static::$SET_CACHE_PWD:
                $result = $this->smsService->send(sprintf('【我是理财师】您的验证码是%s。如非本人操作，请忽略本短信', $code), $telephone);
                break;
            default:
                break;
        }
        if ($this->smsService->success($result)) {
            $salt = $this->utilService->randString(6);
            $token = $this->userService->generatePassword($telephone, $salt);
            //Redis::setex($this->userService->getSessionId($telephone).'|vcodes', env('OAUTH_TTL', 3600), $code);
            Cache::put($this->userService->getSessionId($telephone).'|vcodes', $code, env('SMS_TTL', 5));
            //Redis::set($this->userService->getSessionId($telephone).'|vtoken', $token);
            Cache::put($this->userService->getSessionId($telephone).'|vtoken', $token, env('SMS_TTL', 5));
            return response()->json([
                'status' => 200,
                'data' => [
                    'token' => $token,
                    'telephone' => $telephone
                ]
            ]);
        } else if ($this->smsService->beyondLimit($result)) {
            return response()->json([
                'status' => 400,
                'error' => [
                    'code' => '030002',
                    'message' => '超限.'
                ]
            ], env('CLIENT_ERROR_CODE', 400));
        } else {
            return response()->json([
                'status' => 500,
                'error' => [
                    'code' => '030003',
                    'message' => 'Sms failed.'
                ]
            ], env('SERVER_ERROR_CODE', 500));
        }

    }

}
