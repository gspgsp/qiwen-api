<?php
/**
 * Created by PhpStorm.
 * User: zc
 * Date: 2017/12/28
 * Time: 13:41
 */
namespace APP\Http\Controllers;
use App\Helper\Token;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Services\UserService;
use App\Services\UtilService;
use App\Http\Controllers\Controller;

class IndexController {
    public function __construct(UserService $userService, UtilService $utilService) {
        $this->userService = $userService;
        $this->utilService = $utilService;
    }
    public function  index(){
        echo "Hello!";
    }
    //首页 改为一个接口
    public function getIndexData(){
        try{
            $userid = $this->userService->getUserId(Token::authorization());
            $userStatus = DB::table('member')->select('verify')->where('userid',$userid)->get();
            //栏目列表
            $category_list = DB::table('category_list')->select("cat_id","cat_name","cat_img","cat_type","cat_url")->orderBy('listorder',"asc")->limit(8)->get();
            foreach ($category_list as $k=>$v) {
                $v->cat_img = isIntactUrl($v->cat_img);
            }
            $len = 2;
            //首页banner
            $bannerList = DB::table("picture as t1")->leftjoin("picture_data as t2","t1.id",'=',"t2.id")->select("t1.title","t1.url","t2.pictureurls")->where('catid',71)->get();
            foreach ($bannerList as $k=>$v){
                $bannerList[$k]->pictureurls = isIntactUrl(json_decode($v->pictureurls)->{'0'}->url);
                //$bannerList[$k]->pictureurls = strpos(json_decode($v->pictureurls)->{'0'}->url,'uploadfile') < 2 ? self::$url.json_decode($v->pictureurls)->{'0'}->url: json_decode($v->pictureurls);
            }
            //热门推荐产品
            $hotProduct = DB::table("piece" )->select("thumb","islink","url","title")->where('catid',46)->get();
            foreach ($hotProduct as $k=>$v){
                $v->thumb = isIntactUrl($v->thumb);
            }
            //为你优选
            $trust = DB::table("trust")->select("id","shortName","salesStatus","keywords","states","catid","startAmount","rebate","collectProgress","expectedReturn","fundPeriod")
                ->where("pushOption",",1,")
                ->OrderBy("inputtime","DESC")
                ->Limit($len)
                ->get();
            $simu = DB::table("simu")->select("id","shortName","salesStatus","keywords","states","catid","startAmount","rebate","collectProgress","strategy","totalValue")
                ->where("pushOption",",1,")
                ->OrderBy("inputtime","DESC")
                ->Limit($len)
                ->get();
            $pe = DB::table("pe")->select("id","shortName","salesStatus","keywords","states","catid","startAmount","rebate","collectProgress","targetScale","fundPeriod")
                ->where("pushOption",",1,")
                ->OrderBy("inputtime","DESC")
                ->Limit($len)
                ->get();
            $pre_data = array_merge($trust,$simu,$pe);
            $preferenceData = Controller::isLoginByProduct($pre_data,$userid,$userStatus,0);
            $account  = DB::table("information")->select(DB::raw('sum(exp_commission) as account_rate'),DB::raw('sum(money) as account_money'))->get();
            $yesterday_account = app('db')->select("select sum(exp_commission) as account_rate  from v9_information WHERE TO_DAYS(NOW())-TO_DAYS(FROM_UNIXTIME(create_time)) <=1");
            $account_money = env('ACCOUNT_MONEY')+$account[0]->account_money/10000;
            $account_rate = env('ACCOUNT_REBATE')+$account[0]->account_rate/10000;
            $yesterday_account = env('YESTERDAY_REBATE')+$yesterday_account[0]->account_rate;
            $data = array('banner'=>$bannerList,
                'hotproduct'=>$hotProduct,
                'preference'=>$preferenceData,
                'category_list'=>$category_list,
                'account_money'=>number_format($account_money),
                'account_rate'=>number_format($account_rate),
                'yesterday_rate'=>number_format($yesterday_account),
                'yesterday'=>date("m月d日",strtotime("-1 day"))
            );
            return response()->json(['status'=>200,'data'=>$data]);
        }catch (\Exception $e){
            return response()->json([
                'status' => 500,
                'error' => [
                    'code' => '100001',
                    'message' => 'Internal server error.'
                ]
            ]);
        }
    }
    //首页banner
    public function getIndexBannerList(){
        try{
            $data = DB::table("picture")->where('catid',44)->get();
            return response()->json(['status' => 200,
                'data'=>$data]);
            }catch (\Exception $e){
                return response()->json([
                    'status' => 500,
                    'error' => [
                        'code' => '100001',
                        'message' => 'Internal server error.'
                    ]
                ]);
            }

    }
    //热门产品
    public function getHotProduct(){
        try{
            $data = DB::table("piece")->where('catid',46)->get();
            return response()->json(['status' => 200,
                'data'=>$data]);
        }catch (\Exception $e){
            return response()->json([
                'status' => 500,
                'error' => [
                    'code' => '100002',
                    'message' => 'Internal server error.'
                ]
            ]);
        }
    }
    //1信托 2私募 3 pe
    public function getPreferenceProduct(Request $request){
        $userid = $this->userService->getUserId(Token::authorization());

        try{
            $userStatus = DB::table('member')->select('verify')->where('userid',$userid)->get();
            $len = 2;
            $data = array();
            $trust = DB::table("trust")->where("pushOption",",1,")
                ->OrderBy("inputtime","DESC")
                ->Limit($len)
                ->get();
            $simu =   DB::table("simu")->where("pushOption",",1,")
                ->OrderBy("inputtime","DESC")
                ->Limit($len)
                ->get();
            $pe = DB::table("pe")->where("pushOption",",1,")
                ->OrderBy("inputtime","DESC")
                ->Limit($len)
                ->get();
            $data = array_merge($trust,$simu,$pe);
            $data = Controller::isLoginByProduct($data,$userid,$userStatus,0);
            return response()->json(['status' => 200,
                'data'=>$data]);
        } catch (\Exception $e){
            return response()->json([
                'status' => 500,
                'error' => [
                    'code' => '100003',
                    'message' => 'Internal server error.'
                ]
            ]);
        }



    }
}