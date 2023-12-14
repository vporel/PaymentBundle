<?php
namespace PaymentBundle\PaymentApi;

use PaymentBundle\Entity\Payment;
use Symfony\Component\HttpFoundation\Request;

class MtnApi extends AbstractPaymentApi{

    public function startPayment(Payment $payment, Request $request): array{
        return [];
    }


    public function handleNotification(Request $request): array
    {
        return [];
    }

    public function getName(): string{
        return "mtn";
    }

    
    public function getPaymentStatus(array $paymentInfos): ?string{
        return "";
    }
}