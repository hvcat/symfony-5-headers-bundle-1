<?php 

namespace Constantinos\SecurityHeadersBundle\Library;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleRetry\GuzzleRetryMiddleware;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use Constantinos\SecurityHeadersBundle\Helper\CacheConstantHelper;
use Constantinos\SecurityHeadersBundle\Exception\CelestaClientException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\RedisAdapter;


class CelestaClient
{   
    
    public const GATEWAY_ENDPOINT_AUTHENTICATION = '/oauth/2.0/token';
    private const GATEWAY_ENDPOINT_PLAYER_INFORMATION = '/player/information';

    public const GATEWAY_CACHE_AUTHENTICATION_KEY_TOKEN = 'gateway-cache-authentication-key-token';

    private ?string $applicationToken = null;

    private LoggerInterface $logger;

    private Client $client;

    public function __construct(
        private string $brand,
        private string $version,
        private string $apiUrl,
        private string $clientId,
            private string $clientSecret,
            private RedisAdapter $appCache,
            LoggerInterface $celestaClientLogger,
    ) {

        $this->logger = $celestaClientLogger;
       
        $stack = HandlerStack::create();
        $stack->push(GuzzleRetryMiddleware::factory());
        $this->client = new Client([
            'handler' => $stack,
            'retry_enabled' => false,
        ]);
    }

    /**
     * @throws CelestaClientException
     */
    public function __call(string $name, array $arguments)
    {   dump('heeeeeee');
        $this->authentication();
        
        return $this->$name(...$arguments);
    }
     /**
     * @throws CelestaClientException
     */
    private function request(string $method, string $uri, array $options = [], bool $logRequest = true): ?array
    {
        if (!isset($options['headers']['User-Agent'])) {
            $options['headers']['User-Agent'] = sprintf(
                '%s %s/%s',
                'Guzzle6',
                $this->brand,
                $this->version
            );
        }
        try {
            $response = $this->client->request($method, $uri, $options);
            $data = $response->getBody()->getContents();

            if ($logRequest) {
                $this->logger->debug('celesta.response', [
                    'method' => $method,
                    'uri' => $uri,
                    'request' => $options,
                    'response' => $data,
                ]);
            }

            if ('' === $data) {
                return null;
            }

            return json_decode($data, true, 512, JSON_THROW_ON_ERROR);
        } catch (RequestException $e) {
            $level = $this->logger->debug('Debug');

            if ($e instanceof ServerException) {
                $level =   $this->logger->critical('Critical', [
                    // include extra "context" info in your logs
                    'cause' => 'in_hurry',
                ]);
            }
            $message = $e->getMessage();
            if ($e->hasResponse()) {
                $message = $e->getResponse()?->getBody()->getContents();
            }
            $this->logger->log($level, 'celesta.response.error', [
                'method' => $method,
                'uri' => $uri,
                'request' => $options,
                'result' => $message,
            ]);

            throw new CelestaClientException($message, $e->getResponse()?->getStatusCode() ?? $e->getCode(), $e);
        }
    }

/**
     * @throws CelestaClientException
     */
    private function authentication(): void
    {   
        $itemCache = $this->appCache->getItem(self::GATEWAY_CACHE_AUTHENTICATION_KEY_TOKEN);

        if (!$itemCache->isHit()) {
            $endpoint = sprintf($this->apiUrl.'%s', self::GATEWAY_ENDPOINT_AUTHENTICATION);
            $res = $this->request('POST', $endpoint, [
                'json' => [
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                ],
            ]);

            $this->applicationToken = $res['access_token'];
            $itemCache->set($res['access_token']);
            $itemCache->expiresAfter($res['expires_in'] - CacheConstantHelper::TTL_5_MIN);
            $this->appCache->save($itemCache);
        } else {
            $this->applicationToken = $itemCache->get();
        }
    }

    /**
     * @throws CelestaClientException
     */
    private function getPlayerInformation(string $playerToken): array
    {
        $endpoint = sprintf($this->apiUrl.'%s', self::GATEWAY_ENDPOINT_PLAYER_INFORMATION);

        return $this->request('GET', $endpoint, [
            'headers' => ['Authorization' => 'Bearer '.$playerToken],
        ]);
    }

}