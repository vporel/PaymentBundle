<?php
namespace PaymentBundle\PaymentApi;

use PaymentBundle\Entity\Payment;
use Stripe\StripeClient;
use Stripe\Webhook;
use Symfony\Component\HttpFoundation\Request;
use Stripe\Checkout\Session as CheckoutSession;
use Stripe\Exception\InvalidRequestException;

/**
 * General method for visa, mastercard
 */
class StripeApi extends AbstractPaymentApi{
    /** @var StripeClient */
    private $client;
    /** @var string */
    private $webhookSecret;

    protected function init()
    {
        $this->client = new StripeClient($this->parameterBag->get("payments")["stripe"]["api_key"]);
        $this->webhookSecret =  $this->parameterBag->get("payments")["stripe"]["webhook_secret"];
    }

    public function startPayment(Payment $payment, Request $request): array{
        $checkoutSession = $this->client->checkout->sessions->create([
            "payment_method_types" => ["card"],
            "line_items" => [
                [
                    "quantity" => 1,
                    "price_data" => [
                        "currency" => "XAF",
                        "product_data" => [
                            "name" => $payment->__toString()
                        ],
                        "unit_amount" => $payment->getAmount()
                    ]
                ]
            ],
            "mode" => "payment",
            "cancel_url" => $payment->getCancelURL($this->urlGenerator),
            "success_url" => $payment->getReturnURL($this->urlGenerator)
        ]);
        return [
            "url" => $checkoutSession->url,
            "order-id" => $checkoutSession->id
        ];
    }
    
    public function getName(): string{
        return "stripe";
    }

    public function getPaymentStatus(array $paymentInfos): ?string{
        try{
            $checkoutSession = $this->client->checkout->sessions->retrieve($paymentInfos["order-id"]); //order-id = session-id
            if($checkoutSession->status == "expired") return "EXPIRED";
            switch($checkoutSession->payment_status){
                case "paid": return "SUCCESS";
                case "canceled": return "CANCELLED";
                case "unpaid": return "PENDING";
                case "incomplete": return "FAILED";
            }
        }catch(InvalidRequestException $e){
            //Session id unkown
        }
        return null;
    }

    public function handleNotification(Request $request): array{
        $event = Webhook::constructEvent(
            $request->getContent(), 
            $request->headers->get("stripe-signature"),
            $this->webhookSecret
        );
        if($event->type == "checkout.session.completed"){
            $paymentInfos = $this->serverGetSavedPayment($event->data["object"]["id"]);
            $status = "SUCCESS";
            return [$paymentInfos, $status];
        }
        return [null, null];
    }
}