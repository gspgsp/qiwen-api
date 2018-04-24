<?php
/**
 * Created by PhpStorm.
 * User: txz
 * Date: 2018/1/5
 * Time: 17:00
 */
namespace App\Services;
require_once base_path().'/vendor/qiniu/php-sdk/autoload.php';
use Qiniu\Auth;

class QiNiuService{
    private static $AK = 'Wlzx_o-SAmn38Hp43BgOrw1YGrci8oNIo7GHGIzK';
    private static $SK = 'SmA6FsaIhKf0q3_4ZrLu-2vqpfFcqbxlcYq-RU95';
    public static function getQiNIuInstance(){
        $accessKey = self::$AK;
        $secretKey = self::$SK;
        $qi_niu = new Auth($accessKey,$secretKey);
        return $qi_niu;
    }
}