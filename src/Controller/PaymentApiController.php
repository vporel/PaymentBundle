<?php
namespace PaymentBundle\Controller;

use PaymentBundle\PaymentService;
use RootBundle\Controller\AbstractApiController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route("/api/payments", name:"api.payments.", priority: 100)]
class PaymentApiController extends AbstractApiController {

    public function __construct(private PaymentService $paymentService){}

    /**
     * Get the status of the current payment in the user session
     */
    #[Route("/current-payment-status", name:"currentpaymentstatus", methods:["GET"], priority: 100)]
    public function getCurrentPaymentStatus(Request $request){
        $this->paymentService->bindSession($request->getSession());
        $paymentStatus = $this->paymentService->getCurrentPaymentStatus();
        $info = $request->query->get("info");
        if($info == "c" || $paymentStatus == "INITIATED"){ // "c" == "cancel"
            $paymentStatus = "CANCELLED"; // the user has cancelled the paiement at initialisation
            $this->paymentService->endCurrentPayment("CANCELLED");
        }
        return new JsonResponse($this->success($paymentStatus));
    }

    /**
     * Get the status of a specific payment (doesn't depend on a user session)
     * The request query should contain the paymentMethod and the orderId
     */
    #[Route("/payment-status", name:"paymentstatus", methods:["GET"], priority: 100)]
    public function getPaymentStatus(Request $request){
        $this->paymentService->bindSession($request->getSession());
        $paymentMethod = $request->query->get("paymentMethod");
        $orderId = $request->query->get("orderId");
        $paymentStatus = $this->paymentService->getPaymentStatus($paymentMethod, $orderId);
        return new JsonResponse($this->success($paymentStatus));
    }

    #[Route("/cancel-current-payment", name:"cancelcurrent", methods:["POST"], priority: 100)]
    public function cancelCurrentPayment(Request $request){
        $this->paymentService->bindSession($request->getSession());
        $this->paymentService->endCurrentPayment();
        return new JsonResponse($this->success());
    }

    #[Route("/webhook", name:"webhook", priority: 100)]
    public function webhook(Request $request){
        $this->paymentService->bindSession($request->getSession());
        return $this->paymentService->handleWebhookNotification($request);
    }
}