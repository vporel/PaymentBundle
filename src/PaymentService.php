<?php

namespace PaymentBundle;

use Doctrine\ORM\EntityManagerInterface;
use PaymentBundle\Entity\Payment;
use PaymentBundle\PaymentApi\MtnApi;
use PaymentBundle\PaymentApi\OrangeWebPaymentApi;
use PaymentBundle\PaymentApi\OrangeUssdApi;
use PaymentBundle\PaymentApi\AbstractPaymentApi;
use PaymentBundle\PaymentApi\StripeApi;
use PaymentBundle\WebhookManager\AbstractWebhookManager;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * @author Vivian NKOUANANG (https://github.com/vporel) <dev.vporel@gmail.com>
 */
class PaymentService{
    private const CURRENT_PAYMENT_SESSION_KEY = "current-payment-infos";

    /**
     * @var AbstractPaymentApi[]
     */
    private $apis;
    /**
     * @var Session
     */
    private $session = null;

    public function __construct(
        private ParameterBagInterface $parameterBag, private EntityManagerInterface $em,
        OrangeWebPaymentApi $orangeWebPaymentApi, OrangeUssdApi $orangeUssdApi, MtnApi $mtnApi, StripeApi $stripeApi
    ){
        $this->apis = [
            "orange-web-payment" => $orangeWebPaymentApi, 
            "orange-ussd" => $orangeUssdApi, 
            "mtn" => $mtnApi, 
            "stripe" => $stripeApi
        ];
    }

    /**
     * @param Session $session Current user session
     * @return self
     */
    public function bindSession(Session $session){
        $this->session = $session;
        foreach($this->apis as $api) $api->bindSession($session);
        return $this;
    }

    private function getSession(): Session{
        if($this->session == null)
            throw new \Exception("No session bound. Call the method 'bindSession'");
        return $this->session;
    }
    
    /**
     * Start the payment operation
     * @param string $paymentMethod
     * @param Payment $payment
     * @param Request $request
     * 
     * @return string|array If array, there's an error [$errorMessage, $errorCode]
     */
    public function startPayment(string $paymentMethod, Payment $payment, Request $request){
        if(($paymentMethod ?? "") == "") return ["No payment method provided", "NO_PAYMENT_METHOD"];
        try{
            if($payment->getAmount() == 0)
                throw new PaymentException(PaymentException::PAYMENT_AMOUNT_IS_ZERO);
            if($this->hasPaymentInProgress()){
                if($_ENV["APP_ENV"] == "test") $this->endCurrentPayment("FAILED");
                $paymentStatus = $this->getCurrentPaymentStatus();
                if($paymentStatus != "PRE-INITIATED" && $paymentStatus != "INITIATED" && $paymentStatus != "PENDING")
                    $this->endCurrentPayment($paymentStatus);
                else
                    throw new PaymentException(PaymentException::ANOTHER_PAYMENT_IN_PROGRESS);
            }
            $payment->setMethod($paymentMethod);
            $paymentApi = $this->getPaymentApi($paymentMethod);
            $paymentInfos = $paymentApi->startPayment($payment, $request);
            if(!array_key_exists("url", $paymentInfos)) throw new \Exception("The payment infos must contain the key 'url'");
            if(!array_key_exists("order-id", $paymentInfos)) throw new \Exception("The payment infos must contain the key 'order-id'");
            $payment->setOrderId($paymentInfos["order-id"]);
            $paymentInfos = array_merge($paymentInfos, [
                "user-id" => $payment->getUser()->getId(),
                "amount" => $payment->getAmount(),
                "method" => $paymentMethod,
                "linked-entity-class" => get_class($payment->getLinkedEntity()),
                "linked-entity-id" => $payment->getLinkedEntity()->getId(),
            ]);
            $this->setCurrentPayment($payment, $paymentInfos);
            return $paymentInfos["url"];
        }catch(PaymentException $e){
            if($e->getCode() == PaymentException::ANOTHER_PAYMENT_IN_PROGRESS)
                return [$this->getCurrentPaymentInfos()["url"], "ANOTHER_PAYMENT_IN_PROGRESS"]; //The errorMessage is the current payment url
            return [$e->getMessage(), $e->getCode()];
        }
    }

    /**
     * Get the current payment status from the payment api
     * If the payment is finished, we automatically end the ccurent payment session
     */
    public function getCurrentPaymentStatus(){
        $paymentInfos = $this->getCurrentPaymentInfos();
        if($paymentInfos == null) return "";
        $paymentApi = $this->getPaymentApi($paymentInfos["method"]);
        $paymentStatus = $paymentApi->getPaymentStatus($paymentInfos);
        return $this->normalizePaymentStatus($paymentStatus);
    }

    /**
     * Set in the session the current payment informations
     * @param Payment $payment
     * @param array $paymentInfos Some informations that can be necessary for the used payment method
     */
    private function setCurrentPayment(Payment $payment, array $paymentInfos){
        $this->getPaymentApi($payment->getMethod())->serverSavePayment($paymentInfos);
        $this->getSession()->set(self::CURRENT_PAYMENT_SESSION_KEY, $paymentInfos);
    }

    /**
     * Get the current payment session informations
     */
    public function getCurrentPaymentInfos(){
        return $this->getSession()->get(self::CURRENT_PAYMENT_SESSION_KEY);
    }

    /**
     * Get the current payment status from the payment api
     * If the payment is finished, we automatically end the ccurent payment session
     */
    public function getPaymentStatus(string $paymentMethod, string $orderId){
        $paymentApi = $this->getPaymentApi($paymentMethod);
        $paymentInfos = $paymentApi->serverGetSavedPayment($orderId);
        if($paymentInfos == null) return "NO_PAYMENT";
        $paymentStatus = $paymentApi->getPaymentStatus($paymentInfos);
        return $this->normalizePaymentStatus($paymentStatus);
    }

    /**
     * @return bool If there's an existing payment session
     */
    public function hasPaymentInProgress(){
        return $this->getCurrentPaymentInfos() != null;
    }

    /**
     * End the current payment session with its status
     */
    public function endCurrentPayment(){
        $session = $this->getSession();
        $paymentInfos = $this->getCurrentPaymentInfos();
        if($paymentInfos){
            $this->getPaymentApi($paymentInfos["method"])->serverEndPayment($paymentInfos["order-id"]);
            $session->remove(self::CURRENT_PAYMENT_SESSION_KEY);
        }
    }

    public function handleNotification(Request $request, Payment $payment, callable $onSuccess): bool{
        $method = $request->query->get("method");
        [$paymentInfos, $status] = $this->getPaymentApi($method)->handleNotification($request);
        if($paymentInfos == null) throw new \Exception("Error : No payment information received");
        if($status == "SUCCESS"){
            $payment->setMethod($method);
            $payment->setOrderId($paymentInfos["order-id"]);
            $onSuccess($payment, $this->em);
            return true;
        }
        return false;
    }

    /**
     * For the payments methods that use webhooks like 'stripe'
     */
    public function handleWebhookNotification(Request $request){
        $method = $request->query->get("method");
        [$paymentInfos, $status] = $this->getPaymentApi($method)->handleNotification($request);
        if($paymentInfos != null){
            if($status == "SUCCESS"){
                /** @var AbstractWebhookManager */
                $manager = null;
                foreach($this->parameterBag->get("payments")["webhook_managers"] as $managerClass){
                    $manager = new $managerClass();
                    if($manager->getLinkedEntityClass() == $paymentInfos["linked-entity-class"]) break;
                    else $manager = null;
                }
                if($manager == null)
                    throw new \Exception("There's no configured webhook manager for this payment data");
                $payment = $manager->createPaymentObject($paymentInfos, $this->em);
                $payment->setMethod($method);
                $payment->setOrderId($paymentInfos["order-id"]);
                $manager->onSuccess($payment, $this->em);
                return new Response("success");
            }
        }else{
            return new Response("Error : No payment information received", Response::HTTP_BAD_REQUEST);
        }
        return new Response("No content", Response::HTTP_NO_CONTENT);
                
    }

    /**
     * The payment method indentified by an int
     * @param int  $paymentMethod The payment method int identifier
     */
    private function getPaymentApi(string $paymentMethod): AbstractPaymentApi{
        foreach($this->apis as $api){
            if($paymentMethod == $api->getName()) return $api;
        }   
        throw new PaymentException(PaymentException::UNKNOWN_PAYMENT_METHOD);
    }

    private function normalizePaymentStatus(string $paymentStatus): string{
        if($paymentStatus == "SUCCESSFUL") return "SUCCESS";
        if($paymentStatus == "EXPIRED") return "CANCELLED";
        return $paymentStatus;
    }

}