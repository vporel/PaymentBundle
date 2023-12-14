<?php
namespace PaymentBundle;

/**
 * @author Vivian NKOUANANG (https://github.com/vporel) <dev.vporel@gmail.com>
 */
class PaymentException extends \Exception{
    public const UNKNOWN_ERROR = -1;
    public const ANOTHER_PAYMENT_IN_PROGRESS = 0;
    public const UNKNOWN_PAYMENT_METHOD = 1;
    public const PAYMENT_AMOUNT_IS_ZERO = 2;

    /**
     * @param string $message For an unknown error
     */
    public function __construct(int $code, string $message = null)
    {
        switch($code){
            case self::ANOTHER_PAYMENT_IN_PROGRESS: $message = "Another payment is in progress";break;
            case self::UNKNOWN_PAYMENT_METHOD: $message = "The payment method is unknown";break;
            case self::PAYMENT_AMOUNT_IS_ZERO: $message = "The payment amount cannot be 0";break;
            case self::UNKNOWN_ERROR: $message = $message ?? "An unknown error occured during the payment process";break;
        }
        if($message == null) throw new \Exception("Unknown Exception Code");
        parent::__construct($message, $code);
    }
}