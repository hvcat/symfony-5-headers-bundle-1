<?php

namespace Constantinos\SecurityHeadersBundle\Services;

use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

class MyService implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    public function myMethod()
    {   
        return 'hello world';
        // Your method code here
    }
}
