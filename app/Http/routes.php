<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/
use App\Helper\Token;

$app->get('/', function () use ($app) {
    return $app->version();
});

//Other
$app->group(['namespace' => 'App\Http\Controllers'], function($app)
{

});

//Guest
$app->group(['namespace' => 'App\Http\Controllers', 'middleware' => ['xss']], function($app)
{
    //获取短信验证码
    $app->post('vcode/get', 'VerificationCodeController@get');
    //验证短信验证码
    $app->post('vcode/verify', 'UserController@verifyCode');
    //注册
    $app->post('auth/register', 'UserController@register');
    //注册-推荐人
    $app->post('auth/refer', 'UserController@get_refer');
    //登录
    $app->post('auth/login', 'UserController@login');
    //忘记密码
    $app->post('auth/reset_pwd', 'UserController@resetPwd');
    //首页banner
    $app->post('index/banner', 'IndexController@get_banner');
    //获取所有的文章
    $app->post('index/get_article', 'IndexController@get_article');
    //获取文章详情
    $app->post('index/get_article_detail', 'IndexController@get_article_detail');
    //解析微信用户信息
    $app->post('index/decodeUserInfo', 'IndexController@decodeUserInfo');
    //验证access_token
    $app->post('index/check_token', 'IndexController@check_token');


    //Geetest行为验证初始化
    $app->get('geetest/start', 'GeetestController@start');
    //Geetest行为验证二次回调
    $app->post('geetest/verify', 'GeetestController@verify');
    //判断是否存在用户
    $app->post('user/is_exist', 'UserController@is_exist_user');

});

//Authorization
$app->group(['namespace' => 'App\Http\Controllers', 'middleware' => ['token', 'xss', 'beforeLog', 'afterLog']], function($app)
{
    //用户信息
    $app->post('user/info', 'UserController@userInfo');
    //上传图片获取token 以及img_url H5用
    $app->post('user/upload/token', 'UserController@upload_token');
    //上传头像
    $app->post('user/upload/thumb', 'UserController@upload_thumb');
    //上传身份证
    $app->post('user/upload/card', 'UserController@upload_card');
    //理财师认证
    $app->post('user/finance/verify', 'UserController@verifyFinancialer');
    //获取理财师 成功 认证信息
    $app->post('user/finance/credit_info', 'UserController@getCreditInfo');
    //添加银行卡-验证
    $app->post('user/bank/verify', 'UserController@bank_verify');
    //添加银行卡-添加
    $app->post('user/bank/add', 'UserController@bank_add');
    //设置提现密码 修改登录密码
    $app->post('user/set/pwd', 'UserController@set_password');
    //修改用户名
    $app->post('user/set/nickname', 'UserController@set_nickname');
    //申请提现
    $app->post('user/cash/apply', 'UserController@cash_apply');
    //申请提现记录
    $app->post('user/cash/log', 'UserController@cash_log');
    //返佣记录
    $app->post('user/commision/log', 'UserController@commision_log');
    //退出
    $app->post('auth/logout', 'UserController@logout');
    //我的报单
    $app->post('user/orderlist', 'UserController@myOrderList');
    $app->post('user/order_detail', 'UserController@myOrderDetail');
    //获取短信验证码(登陆后)
    $app->post('vcode/get_verify_code', 'VerificationCodeController@get_verify_code');
    //验证短信验证码(登陆后)
    $app->post('vcode/verify_code', 'UserController@verifySmsCode');
    //获取联系人
    $app->post('user/get_contacts', 'UserController@get_contacts');

});
