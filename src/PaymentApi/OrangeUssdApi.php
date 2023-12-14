<?php
namespace PaymentBundle\PaymentApi;

use PaymentBundle\Entity\Payment;
use PaymentBundle\PaymentException;
use Symfony\Component\HttpFoundation\Request;

class OrangeUssdApi extends AbstractPaymentApi{

    /** @var string */
    private $token;

    public function init(){
        //Initialize token
        $projectDir = $this->parameterBag->get("kernel.project_dir");
        $tokenPath = $projectDir."/runtime/payments/orange-ussd-tokens.json";
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
            $username = $this->parameterBag->get("payments")["orange-ussd"]["username"];
            $password = $this->parameterBag->get("payments")["orange-ussd"]["password"];
            $base64Encoding = base64_encode("$username:$password");
            $response = $this->httpClient->request("POST", "https://api-s1.orange.cm/token", [
                'headers' => [
                    'Content-Type' => "application/x-www-form-urlencoded",
                    'Authorization' => "Basic $base64Encoding"
                ],
                'verify_peer' => false,
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
        $xAuthToken = $this->parameterBag->get("payments")["orange-ussd"]["x_auth_token"];
        $orderId = uniqid("oussd-"); //oussd = orange-ussd : The api does not allow more than 20 characters for the orderId
        $paymentInitResponse = $this->httpClient->request("POST", "https://api-s1.orange.cm/omcoreapis/1.0.2/mp/init", [
            "headers" => [
                "X-AUTH-TOKEN" => $xAuthToken,
                "Authorization" => "Bearer ".$this->token,
            ],
            'verify_peer' => false,
        ]);
        $payToken = json_decode($paymentInitResponse->getContent(), true)["data"]["payToken"];

        $payResponse = $this->httpClient->request("POST", "https://api-s1.orange.cm/omcoreapis/1.0.2/mp/pay", [
            "headers" => [
                "X-AUTH-TOKEN" => $xAuthToken,
                "Authorization" => "Bearer ".$this->token,
                "Accept" => "application/json",
                "Content-Type" => "application/json"
            ],
            'verify_peer' => false,
            "json" => [
                "currency" => "XAF",
                "channelUserMsisdn" =>(string) $this->parameterBag->get("payments")["orange-ussd"]["merchant_phone_number"],
                "subscriberMsisdn" => (string) $request->request->getInt("phoneNumber"),
                "orderId" => $orderId,
                "amount" => $payment->getAmount(),
                "description" => $payment->__toString(),
                "payToken" => $payToken,
                "pin" => $this->parameterBag->get("payments")["orange-ussd"]["pin"],
                "notifUrl" => $payment->getNotifURL($this->urlGenerator)."&method=orange-ussd",
            ]
        ]);
        if($payResponse->getStatusCode() == 200){
            $payResponse = json_decode($payResponse->getContent(), true);
            return [
                "url" => $this->urlGenerator->generate("payments.wait", ["paymentMethod" => "orange-ussd", "orderId" => $orderId, "cancelUrl" => $payment->getCancelURL($this->urlGenerator), "successUrl" => $payment->getSuccessURL($this->urlGenerator)]),
                "pay-token" => $payToken,
                "order-id" => $orderId
            ];
        }else{
            if($payResponse->getStatusCode() == 417) throw new PaymentException(PaymentException::UNKNOWN_ERROR, "Make sure that you have enough money and your number is correct.");
            else throw new PaymentException(PaymentException::UNKNOWN_ERROR);
        }
    }

    public function handleNotification(Request $request): array{
        $requestData = json_decode($request->getContent(), true);
        $status = $requestData["data"]["status"];
        $paymentInfos = $this->serverGetSavedPayment($requestData["order-id"]);
        if(($requestData["pay-token"] ?? null) == $paymentInfos["pay-token"])
            return [$paymentInfos, $status];
        return [null, null];
    }

    public function getName(): string{
        return "orange-ussd";
    }
    
    public function getPaymentStatus(array $paymentInfos): ?string{
        $response = $this->httpClient->request("GET", "https://api-s1.orange.cm/omcoreapis/1.0.2/mp/paymentstatus/".$paymentInfos["pay-token"], [
            "headers" => [
                "X-AUTH-TOKEN" => $this->parameterBag->get("payments")["orange-ussd"]["x_auth_token"],
                "Authorization" => "Bearer ".$this->token
            ],
            'verify_peer' => false,
        ]);
        $response = json_decode($response->getContent(), true);
        return $response["data"]["status"];
    }
}