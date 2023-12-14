<?php
namespace PaymentBundle\PaymentApi;

class PaymentApiException extends \Exception{

    public function __construct(?int $code)
    {
        $message = null;
        switch($code){
            //case self:: : $message = " ";break;
        }
        if($message == null) throw new \Exception("Unknown Exception Code");
        parent::__construct($message, $code);
    }
}