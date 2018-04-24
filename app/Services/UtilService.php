<?php
namespace App\Services;
use Qiniu\Auth;

class UtilService {

    /**
     * 生成随机字符串
     * @param len int 生成串的长度
     * @return string
     *
     * @since 2016-06-20
     */
    public function randString($len) {
        $charset = 'abcdefghkmnprstuvwyzABCDEFGHKLMNPRSTUVWYZ23456789';
        $str = '';
        for ($i = 0; $i < $len; $i++) {
            $str .= $charset[rand(0,48)];
        }
        return $str;
    }

    /**
     * 生成随机数字串
     * @param len int 生成串的长度
     * @return string
     *
     * @since 2016-06-20
     */
    public function randNumberString($len) {
        $charset = '0123456789';
        $str = '';
        for ($i = 0; $i < $len; $i++) {
            $str .= $charset[rand(0,9)];
        }
        return $str;
    }

    /**
     * 获取当前时间的毫秒级时间戳
     * @return float
     *
     * @since 2016-06-20
     */
    public static function microtimeFloat() {
        list($usec, $sec) = explode(" ", microtime());
        return ((float) $usec + (float) $sec);
    }

    /**
     * 格式化时间戳，精确到毫秒，x代表毫秒
     * @param $tag
     * @param $time
     * @return mixed
     *
     * @since 2016-06-20
     */
    public static function microtimeFormat($tag, $time) {
        list($usec, $sec) = explode(".", $time);
        $date = date($tag, $usec);
        return str_replace('x', $sec, $date);
    }

    private $ch;

    public function curlInit() {
        $this->ch = curl_init();
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($this->ch, CURLOPT_TIMEOUT, env('CURL_TIMEOUT', 4));
        return $this->ch;
    }

    public function curlSetUrl($url) {
        curl_setopt($this->ch, CURLOPT_URL,$url);
    }

    public function curlSetHeader($headers) {
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $headers);
    }

    public function curlSetOpt($opt) {
        foreach($opt as $k => $v) {
            curl_setopt($this->ch, $k,$v);
        }
    }

    public function curlExec(){
        $result = curl_exec($this->ch);
        curl_close($this->ch);
        return $result;
    }

    private $qiniu;

    private static $AK = 'Wlzx_o-SAmn38Hp43BgOrw1YGrci8oNIo7GHGIzK';
    private static $SK = 'SmA6FsaIhKf0q3_4ZrLu-2vqpfFcqbxlcYq-RU95';

    public function qiniuGetInstance(){
        if (empty($this->qiniu)) {
            $accessKey = self::$AK;
            $secretKey = self::$SK;
            $this->qiniu = new Auth($accessKey, $secretKey);
        }
        return $this->qiniu;
    }

    public function uuid() {
        return md5(uniqid(rand(), true));
    }

    public function empty_return($k, $v) {
        return !empty($k) ? $v : '';
    }
}