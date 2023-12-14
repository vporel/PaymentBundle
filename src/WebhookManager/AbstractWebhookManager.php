<?php
namespace PaymentBundle\WebhookManager;

use Doctrine\ORM\EntityManagerInterface;
use PaymentBundle\Entity\Payment;

abstract class AbstractWebhookManager{

    final public function __construct(){} //Force empty contructor

    abstract public static function getLinkedEntityClass();

    abstract public function createPaymentObject(array $paymentsInfos, EntityManagerInterface $em): Payment;
    
    /**
     * One the payment service has managed the notification and got a status "SUCCESS"
     *
     * @param Payment $payment
     * @param EntityManagerInterface $em
     * @return void
     */
    abstract public function onSuccess(Payment $payment, EntityManagerInterface $em): void;

}