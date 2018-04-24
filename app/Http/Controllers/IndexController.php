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
            return response()->json([
                'status' => 200,
                'data' => [
                    'banners' => $banners
                ]
            ]);
        }
    }
}