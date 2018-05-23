<?php
namespace App\Http\Controllers;
/*use App\Services\Swoole\SwooleClient;
class  test{
    public function test_swoole(){
        $data = array(
            'action' => 'getIdNo',
            'url' => 'http://dev.zkadmin.com/uploadfile/2018/0323/20180323052058697.png'
        );
        $cli = new SwooleClient();
        $cli->connect('127.0.0.1',9501);
        $cli->send($data);
    }
}*/
use Illuminate\Http\Request;

require_once (__DIR__.'/../../Services/XunSearch/sdk/php/lib/XS.php');
class TestController{
	public function index(Request $request){
		header('Content-Type:text/plain;charset=utf-8');
		$xs = new \XS(__DIR__.'/../../Services/XunSearch/sdk/php/app/goods.ini');  // 使用 /path/to/demo.ini
		$tokenizer = new \XSTokenizerScws;
		$search = $xs->search; // 获取 搜索对象
        $query = $request->input('keyword');
        $words = $search->getExpandedQuery($query);
//        $words = $tokenizer->getResult($query);
        print_r($words);
	    /*$search->setQuery($query)
	        ->setLimit(10,0) // 设置搜索语句, 分页, 偏移量
	    ;

	    $docs = $search->search(); // 执行搜索，将搜索结果文档保存在 $docs 数组中
	    // $docs = $search->getExpandedQuery($query);
	    $count = $search->count(); // 获取搜	索结果的匹配总数估算值
	    foreach ($docs as $doc){
	        $goods_id = $doc->goods_id; // 高亮处理 subject 字段
	        $goods_name = $doc->goods_name; // 高亮处理 subject 字段
	        $goods_remark = $doc->goods_remark; // 高亮处理 message 字段
	        echo $doc->rank().'商品ID: '.$goods_id . '. ' . $goods_name . " [" . $doc->percent() . "%] - ";
	        echo date("Y-m-d", time()) . "<br>" . $goods_remark . "<br>";
	        echo '<br>========<br>';
	    }
	    echo  '总数:'. $count;*/

	}
}