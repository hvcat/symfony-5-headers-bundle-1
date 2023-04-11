<?php 

namespace Constantinos\SecurityHeadersBundle\Library;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleRetry\GuzzleRetryMiddleware;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use Constantinos\SecurityHeadersBundle\Exception\CelestaClientException;
use Psr\Log\LoggerInterface;


class CelestaClient
{ 
    private const GATEWAY_ENDPOINT_PLAYER_INFORMATION = '/player/information';

    private LoggerInterface $logger;

    private Client $client;

    public function __construct(
        private string $brand,
        private string $version,
        private string $apiUrl,
        private string $clientId,
        private string $clientSecret,
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
    private function getPlayerInformation(string $playerToken): array
    {
        $endpoint = sprintf($this->apiUrl.'%s', self::GATEWAY_ENDPOINT_PLAYER_INFORMATION);

        return $this->request('GET', $endpoint, [
            'headers' => ['Authorization' => 'Bearer '.$playerToken],
        ]);
    }

}
