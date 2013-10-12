<?php

namespace Sooqini\MailContext;

use Behat\Behat\Context\Initializer\InitializerInterface;
use Behat\Behat\Context\ContextInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class Extension extends Behat\Behat\Extension\Extension {

    public function load(array $config, ContainerBuilder $container)
    {
        // $config contains parameters
    }

}

class MailContextAwareInitializer implements InitializerInterface
{
    private $mink;

    public function __construct(Mink $mink)
    {
        $this->mink = $mink;
    }

    public function supports(ContextInterface $context)
    {
        // in real life you should use interface for that
        return method_exists($context, 'setMink');
    }

    public function initialize(ContextInterface $context)
    {
        $context->setMink($this->mink);
    }
}

return new Extension();
