<?php
//
if ( ! function_exists('config_path'))
{
    /**
     * Get the configuration path.
     *
     * @param  string $path
     * @return string
     */
    function config_path($path = '')
    {
        return app()->basePath() . '/config' . ($path ? '/' . $path : $path);
    }
}


if (! function_exists('trans')) {
    /**
     * Translate the given message.
     *
     * @param  string  $id
     * @param  array   $parameters
     * @param  string  $domain
     * @param  string  $locale
     * @return string
     */
    function trans($id = null, $parameters = [], $domain = 'messages', $locale = null)
    {
        if (is_null($id)) {
            return app('translator');
        }

        return app('translator')->trans($id, $parameters, $domain, $locale);
    }
}


if (! function_exists('bcrypt')) {
    /**
     * Hash the given value.
     *
     * @param  string  $value
     * @param  array   $options
     * @return string
     */
    function bcrypt($value, $options = [])
    {
        return app('hash')->make($value, $options);
    }
}


if (! function_exists('endWith')) {
    /**
     * 第一个是原串,第二个是 部份串
     * @param  [type] $haystack [description]
     * @param  [type] $needle   [description]
     * @return [type]           [description]
     */
    function endWith($haystack, $needle)
    {
        $length = strlen($needle);
        if($length == 0)
        {
          return true;
        }
        return (substr($haystack, -$length) === $needle);
    }
}

if (! function_exists('formatPhoto')) {
    /**
     * Format Photo
     *
     * @param  string $photo
     * @return array
     */
     function formatPhoto($img, $thumb = null, $domain = null)
    {
        if ($img == null) {
            return null;
        }
        if ($thumb == null) {
            $thumb = $img;
        }
        
        //$domain = $domain == null ?  config('app.shop_url') : $domain ;

        $qiniu = \App\Services\QiNiuYun\Qiniu::qiniuGetInstance();
        //$domain = $domain == null ?  config('app.shop_url') : $domain ;
        if(!preg_match('/^http/', $thumb)  &&!preg_match('/^https/', $thumb) ){
            $url = config('qiniu.private_image_url') . "/" . $thumb;
            $thumb = $qiniu->privateDownloadUrl($url);
        }


        if(!preg_match('/^http/', $img)  &&!preg_match('/^https/', $img) ){
            $url = config('qiniu.private_image_url') . "/" . $img;
            $img = $qiniu->privateDownloadUrl($url);
        }         

        return [
            'width'  => null,
            'height' => null,

            //定义图片服务器
            'thumb'  => $thumb,
            'large'  => $img
        ];
    }
}

if (! function_exists('curl_request')) {
    /**
     * CURL Request
     */
    function curl_request($api, $method = 'GET', $params = array(), $headers = [])
    {
        $curl = curl_init();

        switch (strtoupper($method)) {
            case 'GET' :
                if (!empty($params)) {
                    $api .= (strpos($api, '?') ? '&' : '?') . http_build_query($params);
                }
                curl_setopt($curl, CURLOPT_HTTPGET, TRUE);
                break;
            case 'POST' :
                curl_setopt($curl, CURLOPT_POST, TRUE);
                curl_setopt($curl, CURLOPT_POSTFIELDS, $params);

                break;
            case 'PUT' :
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
                curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
                break;
            case 'DELETE' :
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE');
                curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
                break;
        }

        curl_setopt($curl, CURLOPT_URL, $api);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_HEADER, 0);

//        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($curl);

        if ($response === FALSE) {
            $error = curl_error($curl);
            curl_close($curl);
            return FALSE;
        }else{
            // 解决windows 服务器 BOM 问题
            $response = trim($response,chr(239).chr(187).chr(191));
            $response = json_decode($response, true);
        }

        curl_close($curl);

        return $response;
    }
}

if (! function_exists('show_error')) {
    /**
     * Show Error
     */
    function show_error($code, $message)
    {
        $response = response()->json([
            //'error' => true,
            'error_code' => $code,
            'error_desc' => $message
        ]);
        $response->header('X-'.config('app.name').'-ErrorCode', $code);
        $response->header('X-'.config('app.name').'-ErrorDesc', urlencode($message));
        return $response;
    }
}

if (! function_exists('make_semiangle')) {

    /**
     *  将一个字串中含有全角的数字字符、字母、空格或'%+-()'字符转换为相应半角字符
     *
     * @access  public
     * @param   string       $str         待转换字串
     *
     * @return  string       $str         处理后字串
     */
    function make_semiangle($str)
    {
        $arr = array('０' => '0', '１' => '1', '２' => '2', '３' => '3', '４' => '4',
                     '５' => '5', '６' => '6', '７' => '7', '８' => '8', '９' => '9',
                     'Ａ' => 'A', 'Ｂ' => 'B', 'Ｃ' => 'C', 'Ｄ' => 'D', 'Ｅ' => 'E',
                     'Ｆ' => 'F', 'Ｇ' => 'G', 'Ｈ' => 'H', 'Ｉ' => 'I', 'Ｊ' => 'J',
                     'Ｋ' => 'K', 'Ｌ' => 'L', 'Ｍ' => 'M', 'Ｎ' => 'N', 'Ｏ' => 'O',
                     'Ｐ' => 'P', 'Ｑ' => 'Q', 'Ｒ' => 'R', 'Ｓ' => 'S', 'Ｔ' => 'T',
                     'Ｕ' => 'U', 'Ｖ' => 'V', 'Ｗ' => 'W', 'Ｘ' => 'X', 'Ｙ' => 'Y',
                     'Ｚ' => 'Z', 'ａ' => 'a', 'ｂ' => 'b', 'ｃ' => 'c', 'ｄ' => 'd',
                     'ｅ' => 'e', 'ｆ' => 'f', 'ｇ' => 'g', 'ｈ' => 'h', 'ｉ' => 'i',
                     'ｊ' => 'j', 'ｋ' => 'k', 'ｌ' => 'l', 'ｍ' => 'm', 'ｎ' => 'n',
                     'ｏ' => 'o', 'ｐ' => 'p', 'ｑ' => 'q', 'ｒ' => 'r', 'ｓ' => 's',
                     'ｔ' => 't', 'ｕ' => 'u', 'ｖ' => 'v', 'ｗ' => 'w', 'ｘ' => 'x',
                     'ｙ' => 'y', 'ｚ' => 'z',
                     '（' => '(', '）' => ')', '〔' => '[', '〕' => ']', '【' => '[',
                     '】' => ']', '〖' => '[', '〗' => ']', '“' => '[', '”' => ']',
                     '‘' => '[', '’' => ']', '｛' => '{', '｝' => '}', '《' => '<',
                     '》' => '>',
                     '％' => '%', '＋' => '+', '—' => '-', '－' => '-', '～' => '-',
                     '：' => ':', '。' => '.', '、' => ',', '，' => '.', '、' => '.',
                     '；' => ',', '？' => '?', '！' => '!', '…' => '-', '‖' => '|',
                     '”' => '"', '’' => '`', '‘' => '`', '｜' => '|', '〃' => '"',
                     '　' => ' ');

        return strtr($str, $arr);
    }
}

if (! function_exists('keyToPem')) {
    /**
     * key To Pem
     */
    function keyToPem($key, $private=false)
    {
        //Split lines:
        $lines = str_split($key, 65);
        $body = implode("\n", $lines);
        //Get title:
        $title = $private? 'RSA PRIVATE KEY' : 'PUBLIC KEY';
        //Add wrapping:
        $result = "-----BEGIN {$title}-----\n";
        $result .= $body . "\n";
        $result .= "-----END {$title}-----\n";

        return $result;
    }
}

if (! function_exists('unserialize_config')) {
    /**
     * 处理序列化的支付、配送的配置参数
     * 返回一个以name为索引的数组
     *
     * @access  public
     * @param   string       $cfg
     * @return  void
     */
    function unserialize_config($cfg)
    {
        if (is_string($cfg) && ($arr = unserialize($cfg)) !== false)
        {
            $config = array();

            foreach ($arr AS $key => $val)
            {
                $config[$val['name']] = $val['value'];
            }

            return $config;
        }
        else
        {
            return false;
        }
    }
}

if (! function_exists('is_dev')) {
    function is_dev()
    {
        if (app('request')->cookie('78b5od367n99we5w') == '882q20qxt3089s0s') {
            return true;
        }

        return false;
    }
}

if (! function_exists('format_array')) {
    function format_array($array)
    {
        if (is_array($array)) {
            foreach ($array as $key => $value) {
                if ($value === null) {
                    $array[$key] = '';
                } else if (is_array($value)) {
                    $value = format_array($value);
                    if($value === null) {
                        $array[$key] = '';
                    } else {
                        $array[$key] = $value;
                    }
                }
            }
        }

        return $array;
    }
}


if (! function_exists('filterSpecialchar')) {
    /**
     * 正则去除特殊字符
     *
     * @access  public
     * @param   string       $osstr
     * @return  string
     */
    function filterSpecialchar($ostr){
        $regex = "/\/|\~|\!|\@|\#|\\$|\%|\^|\&|\*|\(|\)|\_|\+|\{|\}|\:|\<|\>|\?|\[|\]|\,|\.|\/|\;|\'|\`|\-|\=|\\\|\|/";
        return preg_replace($regex, "", $ostr);
    }
}

if (! function_exists('uuid')) {
    /**
     * Hash the given value.
     *
     * @return string
     */
    function uuid()
    {
        return md5(uniqid(rand(), true));
    }
}

if (! function_exists('microtimeFormat')) {
    /**
     * @param $tag
     * @param $time
     * @return mixed
     */
    function microtimeFormat($tag, $time) {
        list($usec, $sec) = explode(".", $time);
        $date = date($tag, $usec);
        return str_replace('x', $sec, $date);
    }
}

if (! function_exists('microtimeFloat')) {
    /**
     * @return float
     */
    function microtimeFloat() {
        list($usec, $sec) = explode(" ", microtime());
        return ((float) $usec + (float) $sec);
    }
}

if (! function_exists('calculateRatios')) {
    function calculateRatios($numerator, $denominator) {
        $ratios = floor(($numerator / $denominator) * 100);
        return $ratios;
    }
}

if (! function_exists('createCode')) {
    /**
     * 根据用户id生成推荐码
     * @param $user_id
     * @return string
     */
    function createCode($user_id) {
        static $source_string = 'EWX8DG3HQ5FLYZCA4B1NJ2RSTUV6OPI7M9K';
        $num = $user_id;
        $code = '';
        while ( $num > 0) {
            $mod = $num % 35;
            $num = ($num - $mod) / 35;
            $code = $source_string[$mod].$code;
        }
        if(empty($code[3])) {
            $code = str_pad($code,4,'0',STR_PAD_LEFT);
        }
        return $code;
    }
}

if (! function_exists('parseCode')) {
    /**
     * 根据推荐码解析出用户id
     * @param $code
     * @return int
     */
    function parseCode($code) {
        static $source_string = 'EWX8DG3HQ5FLYZCA4B1NJ2RSTUV6OPI7M9K';
        if (strrpos($code, '0') !== false) {
            $code = substr($code, strrpos($code, '0')+1);
        }
        $len = strlen($code);
        $code = strrev($code);
        $num = 0;
        for ($i=0; $i < $len; $i++) {
            $num += strpos($source_string, $code[$i]) * pow(35, $i);
        }
        return $num;
    }
}

if (! function_exists('convertTime')) {
    function convertTime($time) {
        $now = date('Y-m-d', time());
        $yesterday = date('Y-m-d', strtotime("-1 day"));
        if ( $time == $now ) {
            $str = '今天';
        } else if ( $time == $yesterday ) {
            $str = '昨天';
        } else {
            $str = $time;
        }
        return $str;
    }
}

if (! function_exists('generateUserName')) {
    function generateUserName($mobile) {
        $username = 'u'.substr($mobile, 0, 3);
        $charts = "ABCDEFGHJKLMNPQRSTUVWXYZ";
        $max = strlen($charts)-1;
        for($i = 0; $i < 4; $i ++) {
            $username .= $charts[mt_rand(0, $max)];
        }
        $username .= substr($mobile, -4);
        return $username;
    }
}

if (! function_exists('getCurrentDomain')) {
    function getCurrentDomain() {
        return 'http://'.$_SERVER['SERVER_NAME'];
    }
}

if (! function_exists('getDocumentRoot')) {
    function getDocumentRoot() {
        return $_SERVER['DOCUMENT_ROOT'];
    }
}
if (! function_exists('isIntactUrl')) {
    function isIntactUrl($url) {
        if(strpos($url,"/uploadfile/") > 1){
            return $url;
        }else{
            return env('SERVER_URL').$url;
        }
    }
}