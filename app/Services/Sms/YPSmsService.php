<?php
namespace App\Services\Sms;
require_once(__DIR__.'/../../../vendor/yunpian/YunpianAutoload.php');

/**
 * User: sunshengbo
 * Date: 2016/7/12
 * Time: 17:07
 */
class YPSmsService implements SmsService{

    function getInstance() {
        if (isset($this->smsOperator)) {
            return $this->smsOperator;
        } else {
            return new \SmsOperator();
        }
    }

    function send($msg, $telephone) {
        return $this->getInstance()->single_send([
            'mobile' => $telephone,
            'text' => $msg,
        ]);
    }

    public static function beyondLimit($result) {
        static $limitError = [17, 22, 33];
        return in_array($result->responseData['code'], $limitError);
    }

    public static function success($result) {
        return $result->statusCode == 200;
    }

}