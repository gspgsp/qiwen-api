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
}