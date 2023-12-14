<?php
namespace PaymentBundle;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * @author Vivian NKOUANANG (https://github.com/vporel) <dev.vporel@gmail.com>
 */
class PaymentBundle extends AbstractBundle{

    public const METHODS = [
        "orange-web-payment" => "Orange Money Web Payment", 
        "orange-ussd" => "Orange Money USSD", 
        "stripe" => "Stripe"
    ];

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
        ->children()
            ->arrayNode("webhook_managers")->scalarPrototype()->end()->end() //If the application requires payment emthods that use webhooks like 'stripe'
            ->arrayNode("methods")->scalarPrototype()->end()->end()
        ->end();
    }
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $this->checkConfig($config);
        $container->import(dirname(__DIR__)."/config/services.yaml");
        if(in_array("orange-web-payment", $config["methods"])){
            $this->checkEnvVariables("For the payment method Orange Web Payment", ["ORANGE_WEB_PAYMENT_MERCHANT_KEY", "ORANGE_WEB_PAYMENT_BASIC_AUTHORIZATION"]);
            $config["orange-web-payment"] = [
                "merchant_key" => $_ENV["ORANGE_WEB_PAYMENT_MERCHANT_KEY"],
                "basic_authorization" => $_ENV["ORANGE_WEB_PAYMENT_BASIC_AUTHORIZATION"]
            ];
        }
        if(in_array("orange-ussd", $config["methods"])){
            $this->checkEnvVariables("For the payment method Orange USSD", ["ORANGE_USSD_USERNAME", "ORANGE_USSD_PASSWORD", "ORANGE_USSD_X_AUTH_TOKEN", "ORANGE_USSD_MERCHANT_PHONE_NUMBER", "ORANGE_USSD_PIN"]);
            $config["orange-ussd"] = [
                "username" => $_ENV["ORANGE_USSD_USERNAME"],
                "password" => $_ENV["ORANGE_USSD_PASSWORD"],
                "x_auth_token" => $_ENV["ORANGE_USSD_X_AUTH_TOKEN"],
                "merchant_phone_number" => $_ENV["ORANGE_USSD_MERCHANT_PHONE_NUMBER"],
                "pin" => $_ENV["ORANGE_USSD_PIN"],
            ];
        }
        if(in_array("stripe", $config["methods"])){
            //Check if the stripe package is installed
            if(!class_exists(\Stripe\Stripe::class))
                throw new \Exception("You must install the stripe package. Execute 'composer require stripe/stripe-php'");
            $this->checkEnvVariables("For the payment method Stripe", ["STRIPE_API_KEY", "WEBHOOK_SECRET"]);
            $config["stripe"] = [
                "api_key" => $_ENV["STRIPE_API_KEY"],
                "webhook_secret" => $_ENV["WEBHOOK_SECRET"]
            ];
        }
        //Webhook managers
        if(is_array($config["webhook_managers"])){
            foreach($config["webhook_managers"] as $managerClass){
                if(!class_exists($managerClass)) throw new \Exception("The webhook manager class '$managerClass' does not exist");
            }
        }
        $builder->setParameter("payments", $config);

    }

    /**
     * Custom configuration checking method over the default conditions
     *
     * @param array $config
     * @return void
     * @throws InvalidConfigurationException If there's no payment method defined or there's an unkonwn method
     */
    private function checkConfig(array $config){
        //Methods array cannot be empty
        if(count($config["methods"]) == 0)
            throw new InvalidConfigurationException("You must activate at least one payment method in the configuration file 'payment.yaml'");
        //Payment methods
        foreach($config["methods"] as $method){
            if(!in_array($method, array_keys(self::METHODS)))
                throw new InvalidConfigurationException("The payment method '$method' is unknown");
        }
    }

    private function checkEnvVariables(string $extraMsg = "", array $varsNames = []){
        foreach($varsNames as $var){
            if(!array_key_exists($var, $_ENV))
                throw new \Exception("You must define the environment variable '$var' to configure the PaymentBundle. " . $extraMsg);  
    
        }
    }
    
}