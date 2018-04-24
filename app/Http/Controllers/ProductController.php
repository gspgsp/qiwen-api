<?php
/**
 * Created by PhpStorm.
 * User: zc
 * Date: 2017/12/28
 * Time: 17:55
 */
namespace App\Http\Controllers;

use App\Helper\Token;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Services\UserService;
use App\Services\UtilService;
use App\Http\Controllers\Controller;

class ProductController{
    public function __construct(UserService $userService, UtilService $utilService) {
        $this->userService = $userService;
        $this->utilService = $utilService;
    }
    public function  index(){

    }
    public function getCategoryList(Request $request){
        try{
            //查询栏目表中 固定收益 与 浮动收益的 子类  固定收益catid=7 浮动收益 catid=8
            $constant_catid = 7;
            $float_catid = 8;
            $category_list = DB::table('category')->select('catid','catname')->where('parentid',$constant_catid)->orwhere('parentid',$float_catid)->OrderBy('listorder',"asc")->get();
            return response()->json(['status' => 200,
                'data'=>$category_list]);
        }catch (\Exception $e){
            return response()->json([
                'status' => 500,
                'error' => [
                    'code' => '200000',
                    'message' => 'Internal server error.'
                ]
            ]);
        }
    }

    public function getProductList(Request $request){
        try{
            $userid = $this->userService->getUserId(Token::authorization());
            $userStatus = DB::table('member')->select('verify')->where('userid',$userid)->get();
            $catid = $request->input('catid');
            $pagesize = $request->input('pagesize');
            $offset = ($request->input('page')-1) * $pagesize;
            $table = Controller::getProductName($catid);
            if($catid < 15){
                $data = DB::table($table)->select("id","shortName","salesStatus","keywords","states","catid","startAmount","rebate","collectProgress","expectedReturn","fundPeriod")->where('catid',$catid)
                    ->OrderBy("inputtime","DESC")
                    ->skip($offset)
                    ->take($pagesize)
                    ->get();
            }elseif($catid < 17){
                $data = DB::table($table)->select("id","shortName","salesStatus","keywords","states","catid","startAmount","rebate","collectProgress","strategy","totalValue")->where('catid',$catid)
                    ->OrderBy("inputtime","DESC")
                    ->skip($offset)
                    ->take($pagesize)
                    ->get();
            }elseif($catid == 17){
                $data = DB::table($table)->select("id","shortName","salesStatus","keywords","states","catid","startAmount","rebate","collectProgress","targetScale","fundPeriod")->where('catid',$catid)
                    ->OrderBy("inputtime","DESC")
                    ->skip($offset)
                    ->take($pagesize)
                    ->get();
            }
            $count_product = DB::table($table)->select(DB::raw('count(*) as count_product'))->where("catid",$catid)->get();
            $isLastPage = ceil($count_product[0]->count_product /$pagesize ) <= $request->input('page') ? 1:0;
            $data  = Controller::isLoginByProduct($data,$userid,$userStatus,0);
            return response()->json(['status' => 200,
                'data'=>$data,'isLastPage'=>$isLastPage]);
        }catch (\Exception $e){
            return response()->json([
                'status' => 500,
                'error' => [
                    'code' => '200001',
                    'message' => 'Internal server error.'
                ]
            ]);
        }

    }
    public function getProductDetail(Request $request){
        try{
            $userid = $this->userService->getUserId(Token::authorization());
            $userStatus = DB::table('member')->select('verify')->where('userid',$userid)->get();
            $catid = $request->input('catid');
            $id  =  $request->input('id');
            $table = Controller::getProductName($catid);
            if($catid < 15){
                $sql = "select t1.catid,t1.rebate,t1.collectProgress,t2.collectProgressText,t1.shortName,t1.fundCompany,t1.salesStatus,t1.type,t1.investmentArea,t1.offeringSize,t1.custodianBank,t1.fundPeriod,t1.projectSite,t1.startAmount,t1.startDate,t1.expectedReturn,t1.incomeDistribution,t1.introduce,t2.incomeExplain,t2.collectAccount,t2.investmentDirection,t2.repayingSource,t2.windControl,t2.additionalRemarks from v9_".$table." as t1 left join v9_".$table."_data as t2 on t1.id = t2.id  where t1.id=".$id;
                $data = DB::select($sql);
                $data = Controller::isLoginByProduct($data,$userid,$userStatus,1);
                $data[0]->product_type   = 1;
                $data[0]->product_detail = array(
                    array('title'=>"产品名称",'info'=>$data[0]->shortName,'type'=>0),
                    array('title'=>"发行机构",'info'=>$data[0]->fundCompany,'type'=>0),
                    array('title'=>"销售状态",'info'=>$data[0]->salesStatus,'type'=>1),
                    array('title'=>"产品类型",'info'=>$data[0]->type,'type'=>0),
                    array('title'=>"投资领域",'info'=>$data[0]->investmentArea,'type'=>0),
                    array('title'=>"发行规模",'info'=>$data[0]->offeringSize,'type'=>0),
                    array('title'=>"托管银行",'info'=>$data[0]->custodianBank,'type'=>0),
                    array('title'=>"产品期限",'info'=>$data[0]->fundPeriod,'type'=>0),
                    array('title'=>"项目所在地",'info'=>$data[0]->projectSite,'type'=>0),
                    array('title'=>"投资起点",'info'=>$data[0]->startAmount."万",'type'=>1),
                    array('title'=>"起售时间",'info'=>$data[0]->startDate,'type'=>0),
                    array('title'=>"预期收益率",'info'=>strval($data[0]->expectedReturn),'type'=>0),
                    array('title'=>"收益分配方式",'info'=>$data[0]->incomeDistribution,'type'=>0),
                    array('title'=>"收益说明",'info'=>$data[0]->incomeExplain,'type'=>0),
                    array('title'=>"募集账户",'info'=>$data[0]->collectAccount,'type'=>0),
                );
                $data[0]->product_factor = array(
                    array('title'=>"投资方向",'info'=>$data[0]->investmentDirection,'type'=>0),
                    array('title'=>"还款来源",'info'=>$data[0]->repayingSource,'type'=>0),
                    array('title'=>"风控措施",'info'=>$data[0]->windControl,'type'=>0),
                    array('title'=>"补充说明",'info'=>$data[0]->additionalRemarks,'type'=>0)
                );
                $data[0]->strategy = '';
                $data[0]->totalValue = '';
                $data[0]->targetScale = '';
                $data[0]->expectedReturn = number_format(floatval($data[0]->expectedReturn),2)."%";
            }elseif($catid < 17){
                $sql = "select catid,rebate,shortName,collectProgress,totalValue,newValue,registerDate,fundManager,fundCompany,states,tacticForm,organizeForm,custodianBank,publisher,type,dueDate,closedPeriod,fundPeriod,startAmount,strategy,minBuyAmount,addBuyAmount,buyRates,redemptionRates,expensesRates,floatRates,introduce from v9_".$table."  where id=".$id;
                $data = DB::select($sql);
                $data = Controller::isLoginByProduct($data,$userid,$userStatus,1);
                $data[0]->product_type   =  2;
                $data[0]->product_detail = array(
                    array('title'=>"产品名称",'info'=>$data[0]->shortName,'type'=>0),
                    array('title'=>"累计净值",'info'=>$data[0]->totalValue,'type'=>0),
                    array('title'=>"最近净值",'info'=>$data[0]->newValue,'type'=>0),
                    array('title'=>"成立日期",'info'=>$data[0]->registerDate,'type'=>0),
                    array('title'=>"基金经理",'info'=>$data[0]->fundManager,'type'=>0),
                    array('title'=>"所属机构",'info'=>$data[0]->fundCompany,'type'=>0),
                    array('title'=>"基金状态",'info'=>$data[0]->states,'type'=>0),
                    array('title'=>"基金类型",'info'=>$data[0]->type,'type'=>0),
                    array('title'=>"组织形式",'info'=>$data[0]->organizeForm,'type'=>0),
                    array('title'=>"托管银行",'info'=>$data[0]->custodianBank,'type'=>0),
                    array('title'=>"结构形式",'info'=>$data[0]->tacticForm,'type'=>0),
                    array('title'=>"基金发行人",'info'=>$data[0]->publisher,'type'=>0),
                    array('title'=>"封闭到期日",'info'=>$data[0]->dueDate,'type'=>0),
                    array('title'=>"封闭期限",'info'=>$data[0]->closedPeriod,'type'=>0),
                    array('title'=>"产品期限",'info'=>$data[0]->fundPeriod,'type'=>0),
                );
                $data[0]->product_factor = array(
                    array('title'=>"最低认购金额",'info'=>($data[0]->minBuyAmount === '---' ? '---': $data[0]->minBuyAmount."万"),'type'=>0),
                    array('title'=>"追加认购金额",'info'=>($data[0]->addBuyAmount === '---'?'---':$data[0]->addBuyAmount.'万'),'type'=>0),
                    array('title'=>"认购费率",'info'=>($data[0]->buyRates === '---' ? '---':$data[0]->addBuyAmount.'%'),'type'=>0),
                    array('title'=>"赎回费率",'info'=>($data[0]->redemptionRates === '---' ?'---':$data[0]->addBuyAmount.'%' ),'type'=>0),
                    array('title'=>"管理费率",'info'=>($data[0]->expensesRates === '---'? '---' : $data[0]->addBuyAmount.'%/年'),'type'=>0),
                    array('title'=>"浮动管理费率",'info'=>($data[0]->floatRates === '---' ? '---' : $data[0]->addBuyAmount.'%'),'type'=>0)
                );

            }elseif($catid == 17) {
                $sql = "select t1.catid,t1.rebate,t1.fundPeriod,t1.shortName,t1.collectProgress,t1.startAmount,t1.fundPeriod,t1.states,t1.fundCompany,t1.investmentDirection,t1.capitalType,t1.targetScale,t1.ganisRates,t1.duration,t1.startDate,t1.endDate,t1.expensesRates,t1.organizeForm,t1.introduce,t2.investmentMatters,t2.cashType,t2.windControl,t2.companyIntro from v9_".$table." as t1 left join v9_".$table."_data as t2 on t1.id = t2.id  where t1.id=".$id;
                $data = DB::select($sql);
                $data = Controller::isLoginByProduct($data,$userid,$userStatus,1);
                $data[0]->product_type   =  3;
                $data[0]->product_detail = array(
                    array('title' => "产品名称", 'info' => $data[0]->shortName, 'type' => 0),
                    array('title' => "管理机构", 'info' => $data[0]->fundCompany, 'type' => 0),
                    array('title' => "募集状态", 'info' => $data[0]->states, 'type' => 1),
                    array('title' => "投资方向", 'info' => $data[0]->investmentDirection, 'type' => 0),
                    array('title' => "资本类型", 'info' => $data[0]->capitalType, 'type' => 0),
                    array('title' => "目标规模", 'info' => $data[0]->targetScale, 'type' => 0),
                    array('title' => "收益分成比例", 'info' => $data[0]->ganisRates, 'type' => 0),
                    array('title' => "投资门槛", 'info' => $data[0]->startAmount."万", 'type' => 0),
                    array('title' => "存续期限", 'info' => $data[0]->duration, 'type' => 0),
                    array('title' => "开始募集时间", 'info' => $data[0]->startDate, 'type' => 1),
                    array('title' => "管理费率", 'info' => ($data[0]->expensesRates === '---' ? '---' : $data[0]->expensesRates.'%/年'), 'type' => 0),
                    array('title' => "募集完成时间", 'info' => $data[0]->endDate, 'type' => 1),
                    array('title' => "组织形式", 'info' => $data[0]->organizeForm, 'type' => 0),
                    array('title' => "投资事项", 'info' => $data[0]->investmentMatters, 'type' => 0),
                );
                $data[0]->product_factor = array(
                    array('title' => "到款方式", 'info' => $data[0]->cashType, 'type' => 0),
                    array('title' => "风控措施", 'info' => $data[0]->windControl, 'type' => 0),
                    array('title' => "管理机构", 'info' => $data[0]->companyIntro, 'type' => 0)
                );
                $data[0]->totalValue = '';
                $data[0]->strategy = '';
            }
            /* //$data = DB::select("SELECT * FROM ".$table."LEFT JOIN ".$table." ");
             $data =  DB::table($table." as t1")->leftjoin($table."_data as t2","t1.id",'=',"t2.id")
                 ->where('t1.id',$id)->get();
             var_dump($data);
             exit;*/
            $data = Controller::isLoginByProduct($data,$userid,$userStatus,1);
            return response()->json(['status' => 200,
                'data'=>$data]);
        }catch (\Exception $e){
            return response()->json([
                'status' => 500,
                'error' => [
                    'code' => '200002',
                    'message' => 'Internal server error.'
                ]
            ]);
        }

    }
}