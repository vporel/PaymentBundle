<?php
namespace PaymentBundle\Entity;

use RootBundle\Entity\Entity;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use PaymentBundle\PaymentBundle;
use RootBundle\Entity\Trait\TimestampsTrait;
use UserAccountBundle\Entity\User;

/**
 * @ORM\MappedSuperclass
 * @author Vivian NKOUANANG (https://github.com/vporel) <dev.vporel@gmail.com>
 */
abstract class Payment{
    use TimestampsTrait;

     /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    protected $id;

    /**
     * @var float
     * @ORM\Column(type="float")
     */
    protected $amount;

    /**
     * @var string
     * @ORM\Column(type="string")
     */
    protected $method;

    /**
     * Id from the payment api
     * Also called $sessionId for some apis
     * @var string
     * @ORM\Column(type="string")
     */
    protected $orderId;
    /**
     * @var User
     * @ORM\ManyToOne(targetEntity="UserAccountBundle\Entity\UserInterface")
     * @ORM\JoinColumn(name="user_id", referencedColumnName="id")
     */
    protected $user;
    /**
     * @var \DateTime
     * @ORM\Column(type="datetime", options={"default":"CURRENT_TIMESTAMP"})
     */
    protected $createdAt;

    /**
     * @var \DateTime
     * @ORM\Column(type="datetime", options={"default":"CURRENT_TIMESTAMP"})
     */
    protected $updatedAt;

    public function getId(){
        return $this->id;
    }
    
    /**
     * Get the value of method
     *
     * @return  string
     */ 
    public function getMethod() : string
    {
        return $this->method;
    }

    /**
     * Set the value of method
     *
     * @param string  $method
     *
     * @return self
     */ 
    public function setMethod(string $method): self
    {
        if(!in_array($method, array_keys(PaymentBundle::METHODS)))
            throw new \InvalidArgumentException("The payment method '$method' is unknown");
        $this->method = $method;
        return $this;
    }

    public function getMethodText(){
        return PaymentBundle::METHODS[$this->method];
    }

    public function getOrderId(){
        return $this->orderId;
    }

    public function setOrderId(string $orderId){
        $this->orderId = $orderId;
        return $this;
    }

    /**
     * Get the value of amount
     *
     * @return  float
     */ 
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * Set the value of amount
     *
     * @param  float  $amount
     *
     * @return  self
     */ 
    public function setAmount(float $amount)
    {
        $this->amount = $amount;

        return $this;
    }

   
    public function getUser(): User{
        return $this->user;
    }

    public function setUser(User $user): self{
        $this->user = $user;
        return $this;
    }
    
    /**
     * The entity linked to the payment 
     * Ex : for SubscriptionPayment, the linked entity could be a Subscription
     *
     * @return Entity
     */
    abstract public function getLinkedEntity(): Entity;
    /**
     * The string seen by the user on the payment page
     * @return string
     */
    abstract public function __toString(): string;

    abstract public function getPaymentStatusUrl(UrlGeneratorInterface $urlGenerator): string;
    abstract public function getNotifURL(UrlGeneratorInterface $urlGenerator): string;

    final public function getReturnURL(UrlGeneratorInterface $urlGenerator): string{
        return $this->getPaymentStatusUrl($urlGenerator) . "?info=r";
    }
    final public function getCancelURL(UrlGeneratorInterface $urlGenerator): string{
        return $this->getPaymentStatusUrl($urlGenerator) . "?info=c";
    }
    final public function getSuccessURL(UrlGeneratorInterface $urlGenerator): string{
        return $this->getPaymentStatusUrl($urlGenerator) . "?info=s";
    }

}