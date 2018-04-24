<?php 
namespace App\Services\Sms;
interface SmsService {

    public function send($msg, $telephone);

    public static function beyondLimit($result);

    public static function success($result);

}