<?php
/**
 * Created by PhpStorm.
 * User: txz
 * Date: 2018/4/28
 * Time: 15:32
 */
namespace App\Services\WxDataService;

class ErrorCodeService{
    public static $OK = 0;
    public static $IllegalAesKey = -41001;
    public static $IllegalIv = -41002;
    public static $IllegalBuffer = -41003;
    public static $DecodeBase64Error = -41004;
}