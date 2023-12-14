<?php
namespace PaymentBundle\PaymentApi;

use PaymentBundle\Entity\Payment;
use PaymentBundle\PaymentException;
use Symfony\Component\HttpFoundation\Request;

class OrangeWebPaymentApi extends AbstractPaymentApi{

    /** @var string */
    private $token;

    public function init(){
        //Initialize token
        $projectDir = $this->parameterBag->get("kernel.project_dir");
        $tokenPath = $projectDir."/runtime/payments/orange-web-payment-tokens.json";
        $tokenArray = [];
        if(file_exists($tokenPath)){
            $tokenArray = json_decode(file_get_contents($tokenPath), true) ?? [];
        }else{
            file_put_contents($tokenPath, "{}");
            if(!is_writable($tokenPath)){
                unlink($tokenPath);
                throw new \RuntimeException("The file '".$tokenPath."' doesn't exist. Its creation failed");
            }
        }
        if(count($tokenArray) == 0 || new \DateTime($tokenArray["expires_at"]["date"]) < new \DateTime('now')){//No token or token has expired
            //Make a request to get the token
            $authorization = $this->parameterBag->get("payments")["orange-web-payment"]["basic_authorization"];
            $response = $this->httpClient->request("POST", "https://api.orange.com/oauth/v3/token", [
                'headers' => ['Authorization' => "Basic $authorization"],
                'body' => ['grant_type' => 'client_credentials']

            ]);
            $response = json_decode($response->getContent(), true);
            $this->token = $response["access_token"]; //Use expires_in minus five minutes
            $tokenArray = ["token" => $this->token, "expires_at" => (new \DateTime())->add(new \DateInterval("PT".(((int) $response["expires_in"] - 5))."S"))];
            \file_put_contents($tokenPath, json_encode($tokenArray));
        }else{
            $this->token = $tokenArray["token"];
        }
    }

    public function startPayment(Payment $payment, Request $request): array{
        $merchantKey = $this->parameterBag->get("payments")["orange-web-payment"]["merchant_key"];
        $orderId = uniqid("owp-"); //owp = orange-web-payment
        $response = $this->httpClient->request("POST", "https://api.orange.com/orange-money-webpay/cm/v1/webpayment", [
            "headers" => [
                "Authorization" => "Bearer ".$this->token,
                "Accept" => "application/json",
                "Content-Type" => "application/json"
            ],
            "json" => [
                "merchant_key" => $merchantKey,
                "currency" => "XAF",
                "order_id" => $orderId,
                "amount" => $payment->getAmount(),
                "return_url" => $payment->getReturnURL($this->urlGenerator),
                "cancel_url" => $payment->getCancelURL($this->urlGenerator),
                "notif_url" => $payment->getNotifURL($this->urlGenerator)."&method=orange-web-payment",
                "lang" => "fr",
                "reference" => "Lahotte"
            ]
        ]);
        $response = json_decode($response->getContent(), true);
        if($response["message"] == "OK"){
            return [
                "url" => $response["payment_url"],
                "pay-token" => $response["pay_token"],
                "notif-token" => $response["notif_token"],
                "order-id" => $orderId
            ];
        }else{
            throw new PaymentException(PaymentException::UNKNOWN_ERROR);
        }
    }

    public function handleNotification(Request $request): array{
        $requestData = json_decode($request->getContent(), true);
        $status = $requestData["status"];
        $paymentInfos = $this->serverGetSavedPayment($requestData["order-id"]);
        if(($requestData["notif-token"] ?? null) == $paymentInfos["notif-token"])
            return [$paymentInfos, $status];
        return [null, null];
    }

    public function getName(): string{
        return "orange-web-payment";
    }
    
    public function getPaymentStatus(array $paymentInfos): ?string{
        $response = $this->httpClient->request("POST", "https://api.orange.com/orange-money-webpay/cm/v1/transactionstatus", [
            "headers" => [
                "Authorization" => "Bearer ".$this->token,
                "Accept" => "application/json",
                "Content-Type" => "application/json"
            ],
            "json" => [
                "order_id" => $paymentInfos["order-id"],
                "amount" => $paymentInfos["amount"],
                "pay_token" => $paymentInfos["pay-token"],
            ]

        ]);
        $response = json_decode($response->getContent(), true);
        return $response["status"];
    }
}