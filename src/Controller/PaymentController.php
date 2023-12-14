<?php
namespace PaymentBundle\Controller;

use PaymentBundle\PaymentService;
use RootBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route("/payments", name:"payments.", priority: 100)]
class PaymentController extends AbstractController {

    public function __construct(private PaymentService $paymentService){}

    #[Route("/{paymentMethod}", name:"wait", methods:["GET"], requirements: ["paymentMethod" => "[a-z0-9-]+"], priority: 100)]
    public function getCurrentPaymentStatus(Request $request){
        return $this->render("@Payment/wait-payment", ["method" => "orange-ussd"]);  
    }
}