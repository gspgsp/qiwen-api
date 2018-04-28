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

        $curl = curl_init();
        // 使用curl_setopt()设置要获取的URL地址
        $url = "https://api.weixin.qq.com/sns/jscode2session?&appid=".env('WXAPP_ID')."&secret=".env('WXAPP_SECRET')."&js_code=".$request->input('code')."&grant_type=".env('GRANT_TYPE');
        curl_setopt($curl, CURLOPT_URL, $url);
        // 设置是否输出header
        curl_setopt($curl, CURLOPT_HEADER, false);
        // 设置是否输出结果
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        // 设置是否检查服务器端的证书
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        // 使用curl_exec()将CURL返回的结果转换成正常数据并保存到一个变量
        $data = curl_exec($curl);
        // 使用 curl_close() 关闭CURL会话
        curl_close($curl);

        $data = json_decode($data);
        $data = get_object_vars($data);

        Log::debug('response_wx', ['error_code' => $data['errcode'],'data'=>$data]);
        return response()->json([
            'status' => 200,
            'data' => $data,
        ]);
    }
}