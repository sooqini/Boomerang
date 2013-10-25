<?php

namespace Sooqini\Boomerang;

use Behat\Behat\Context\Initializer\InitializerInterface;
use Behat\Behat\Context\ContextInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class Extension extends \Behat\Behat\Extension\Extension {

    public function load(array $config, ContainerBuilder $container)
    {
        $container->setParameter('boomerang.default_service', sprintf('boomerang.%s', @$config['default_service'] ?: 'guerrillamail'));
        unset($config['default_service']);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/services'));
        $loader->load('core.xml');

        foreach($config as $service => $parameters) {
            $loader->load("$service.xml");
            foreach($parameters as $name => $value) {
                $container->setParameter("boomerang.$service.$name", $value);
            }
        }

    }

}

class BoomerangAwareInitializer implements InitializerInterface
{
    private $serviceProvider;

    public function __construct(ServiceProvider $serviceProvider)
    {
        $this->serviceProvider = $serviceProvider;
    }

    public function supports(ContextInterface $context)
    {
        return $context instanceof ServiceConsumer;
    }

    public function initialize(ContextInterface $context)
    {
        $context->setBoomerangServiceProvider($this->serviceProvider);
    }
}

class BoomerangClassGuesser implements ClassGuesserInterface
{
    public function guess()
    {
        return 'BoomerangContext';
    }
}

