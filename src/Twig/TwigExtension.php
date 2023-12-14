<?php
namespace PaymentBundle\Twig;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFilter;

class TwigExtension extends AbstractExtension implements GlobalsInterface{
    
    public function __construct(private ParameterBagInterface $parameterBag)
    {}

    public function getGlobals(): array
    {
        return ["payment_methods" => $this->parameterBag->get("payments")["methods"]];
    }
    
}