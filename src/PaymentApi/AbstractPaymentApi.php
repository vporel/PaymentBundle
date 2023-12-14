<?php 
namespace PaymentBundle\PaymentApi;

use PaymentBundle\Entity\Payment;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Abstract class for the payment methods in the application
 */
abstract class AbstractPaymentApi{
    
    private $currentPayments = null;
    private $currentPaymentsPath = null;

    /**
     * @var Session
     */
    private $session = null;

    final public function __construct(
        protected HttpClientInterface $httpClient, 
        protected UrlGeneratorInterface $urlGenerator,
        protected ParameterBagInterface $parameterBag
    ){
        //Get current payments
        $projectDir = $this->parameterBag->get("kernel.project_dir");
        $this->currentPaymentsPath = $projectDir."/runtime/payments/".$this->getName()."-current-payments.json";

        if(file_exists($this->currentPaymentsPath))
        $this->currentPayments = json_decode(file_get_contents($this->currentPaymentsPath), true) ?? [];
        else{
            file_put_contents($this->currentPaymentsPath, "{}");
            if(!is_writable($this->currentPaymentsPath)){
                unlink($this->currentPaymentsPath);
                throw new \RuntimeException("Le fichier '".$this->currentPaymentsPath."' n'existe pas. Sa création avec le contrôle total a échoué");
            }
        }
        if(array_key_exists($this->getName(), $this->parameterBag->get("payments"))) //Init if the api is required
            $this->init();
    }

    /**
     * Initialise the api client
     * @return void
     */
    protected function init(){
        //Instruction to initialize the API
    }

    /**
     * @param Session $session Current user session
     * @return self
     */
    public function bindSession(Session $session){
        $this->session = $session;
        return $this;
    }

    protected function getSession(): Session{
        if($this->session == null)
            throw new \Exception("No session bound. Call the method 'bindSession'");
        return $this->session;
    }

    /**
     * This function doesn't need a user context
     * @return null|array The informations about a payment in progress in the server context
     */
    final public function serverGetSavedPayment(string $orderId){
        foreach($this->currentPayments as $payment){
            if($payment["order-id"] == $orderId)
                return $payment;
        }
        return null;
    }

    /**
     * Store a payment on the server 
     */
    final public function serverSavePayment(array $paymentInfos){
        $this->currentPayments[] = $paymentInfos;
        \file_put_contents($this->currentPaymentsPath, json_encode($this->currentPayments));
    }

    /**
     * Delete a payment info from the server
     * The payment is only removed from the file that store current payments in progress
     */
    final public function serverEndPayment(string $orderId){
        foreach($this->currentPayments as $key => $payment){
            if($payment["order-id"] == $orderId){
                unset($this->currentPayments[$key]);
                break;
            }
        }
        \file_put_contents($this->currentPaymentsPath, json_encode($this->currentPayments));
    }
    
    /**
     * The payment api name
     * This name is for example used to identify stored tokens
     * @return string
     */
    abstract public function getName(): string;
    
    /**
     * Start a payment
     * @param Payment $payment
     * 
     * @return array  //Payment informations with the redirection url
     */
    abstract public function startPayment(Payment $payment, Request $request): array;

    /**
     * Check if an api payment notification must be managed depending on the data in the request
     * @param Request $request
     * @return array [paymentInfos, status] The payment informations and the status if the payment is authenticate. If not, returns [null, null]
     */
    abstract public function handleNotification(Request $request): array;

    /**
     * Get the status of a payment
     * @param array $paymentInfos
     * 
     * @return string
     */
    abstract public function getPaymentStatus(array $paymentInfos): ?string;
}