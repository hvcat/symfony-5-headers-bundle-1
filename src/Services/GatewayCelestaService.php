<?php

namespace Constantinos\SecurityHeadersBundle\Services;

use Constantinos\SecurityHeadersBundle\Library\CelestaClient;
use Constantinos\SecurityHeadersBundle\Exception\CelestaClientException;
use Constantinos\SecurityHeadersBundle\Exception\GatewayCelestaException;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
class GatewayCelestaService implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    private string $paymentSecretToken;


    public function __construct(
        protected CelestaClient $client,
        private RedisAdapter $appCache,
        private UrlGeneratorInterface $router,
        
        ParameterBagInterface $params
    ) {
        $this->paymentSecretToken = $params->get('payment.secret.token');
    }
    public function myMethod()
    {
        return 'hello world';
        // Your method code here
    }


    /**
     * @throws GatewayCelestaException
     */
    public function getPlayerInformation(string $playerToken): array
    {
        die('celestaBundle');
       
        try {
            $response = $this->client->getPlayerInformation($playerToken);
            unset(
                $response['status'],
                $response['bonusOfferAllowed'],
                $response['username'],
            );

            return $response;
        } catch (CelestaClientException $e) {
            throw new GatewayCelestaException($e->getMessage(), $e->getCode());
        }
    }
}
