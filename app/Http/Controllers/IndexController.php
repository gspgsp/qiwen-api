<?php
/**
 * Created by PhpStorm.
 * User: zc
 * Date: 2017/12/28
 * Time: 13:41
 */
namespace APP\Http\Controllers;
use Illuminate\Http\Request;
use App\Services\UserService;
use App\Services\UtilService;
use App\Services\WxDataService\WxBizDataCryptService;
use App\Helper\Token;
use Carbon\Carbon;
use Cache;
use Log;

class IndexController {
    public function __construct(UserService $userService, UtilService $utilService) {
        $this->userService = $userService;
        $this->utilService = $utilService;
    }
    //首页banner
    public function get_banner(Request $request){
        $banners =  \DB::table('ad')->where('pid',32)->get();
        if($banners){
            foreach ($banners as &$val){
                $val->ad_code = env('IMAGE_DOMAIN').$val->ad_code;
            }
            return response()->json([
                'status' => 200,
                'data' => [
                    'banners' => $banners
                ]
            ]);
        }
    }

    /**获取所有的文章：id：1~8
     * @param Request $request
     */
    public function get_article(Request $request){
        if(empty($request->input('cate_id'))){
            return response()->json([
                'status' => 400,
                'error' => [
                    'code' => '020000',
                    'message' => '请输入文章类型.'
                ]
            ]);
        }
        $dataList = \DB::table('article')->where('cat_id',$request->input('cate_id'))->select('article_id','cat_id','title')
            ->orderBy('add_time','desc')
            ->skip(0)
            ->take($request->input('page_size'))
            ->get();
        return response()->json([
            'status' => 200,
            'data' => [
                'dataList' => $dataList
            ]
        ]);
    }

    /**
     * 获取文章详情页
     * @param Request $request
     */
    public function get_article_detail(Request $request){
        if(empty($request->input('article_id'))){
            return response()->json([
                'status' => 400,
                'error' => [
                    'code' => '020000',
                    'message' => '请输入文章类型.'
                ]
            ]);
        }
        $articleDetail = \DB::table('article')->where('article_id',$request->input('article_id'))->first();
        return response()->json([
            'status' => 200,
            'data' => [
                'articleDetail' => $articleDetail
            ]
        ]);
    }

    /**
     * 解析微信用户信息
     * @param Request $request
     */
    public function decodeUserInfo(Request $request){
        if(empty($request->input('code')) && empty($request->input('encryptedData')) && empty($request->input('iv'))){
            return response()->json([
                'status' => 400,
                'error' => [
                    'code' => '020000',
                    'message' => '参数错误.'
                ]
            ]);
        }
        $api = env('JSCODE2SESSION_URL');
        $params = [
            'appid'   => env('WXAPP_ID'),
            'secret' => env('WXAPP_SECRET'),
            'js_code' => $request->input('code'),
            'grant_type' => env('GRANT_TYPE'),
        ];
        $response = curl_request($api, 'GET', $params, []);
        var_dump($response);die;

        // 使用curl_setopt()设置要获取的URL地址
       /* $url = "https://api.weixin.qq.com/sns/jscode2session?appid=".env('WXAPP_ID')."&secret=".env('WXAPP_SECRET')."&js_code=".$request->input('code')."&grant_type=".env('GRANT_TYPE');
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_HEADER, 0 ); // 过滤HTTP头
        curl_setopt($curl,CURLOPT_RETURNTRANSFER, 1);// 显示输出结果
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);//SSL证书认证
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);//严格认证
        $poem_url = getDocumentRoot().'/cacert.pem';
        curl_setopt($curl, CURLOPT_CAINFO, $poem_url);//证书地址
        $responseText = json_decode(curl_exec($curl),true);
        $error_code = curl_errno($curl);
        $curl_info = curl_getinfo($curl);

        curl_close($curl);*/

        $pc = new WxBizDataCryptService(env('WXAPP_ID'), $response['session_key']);
        $data = '';
        $errCode = $pc->decryptData($request->input('encryptedData'), $request->input('iv'), $data );
        if($errCode == 0){
            $data = json_decode($data, true);
            $user_info = array(
                'openid' => $data['openId'],
                'nickname' => $data['nickName'],
                'sex' => $data['gender'],
                'head_pic' => $data['avatarUrl'],
                'unionid' => $data['unionId'],
                'input_time' => $data['watermark']['timestamp'],
            );
            if($user_id =  \DB::table('users')->insertGetId($user_info)){
                $access_token = Token::encode(['uid' => $user_id]);
                $expiresAt = Carbon::now()->addMinutes(config('token.ttl'));
                Cache::put('access_token:'.$user_id, $access_token, $expiresAt);
                return response()->json([
                    'status' => 200,
                    'data' => [
                        'access_token' => $access_token,
                        'user_info' => $this->userService->getUserInfo($user_id)
                    ]
                ]);
            }else{
                return response()->json([
                    'status' => 400,
                    'error' => [
                        'code' => '020000',
                        'message' => '操作错误.'
                    ]
                ]);
            }
        }else{
            return response()->json([
                'status' => 400,
                'error' => [
                    'code' => $errCode,
                    'message' => '抱歉，未能获取到用户信息.'
                ]
            ]);
        }
    }

    /**
     * 验证access_token
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function check_token(Request $request){
        if(empty($request->input('token'))){
            return response()->json([
                'status' => 400,
                'error' => [
                    'code' => '020000',
                    'message' => '请输入access_token.'
                ]
            ]);
        }
        $payload =  Token::decode($request->input('token'));
        var_dump($payload);

    }

}