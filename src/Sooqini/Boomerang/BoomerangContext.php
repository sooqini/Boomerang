<?php

namespace Sooqini\Boomerang;


use Behat\Behat\Context\BehatContext;

class BoomerangContext extends BehatContext implements ServiceConsumer {
    use BoomerangDictionary;

}