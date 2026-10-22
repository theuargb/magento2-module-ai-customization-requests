<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Infra;

use GuzzleHttp\ClientFactory;
use GuzzleHttp\Exception\TransferException;
use Psr\Log\LoggerInterface;

/** JSON-over-HTTP transport shared by the OpenRouter and OpenSearch clients. */
class JsonHttpClient
{
    public function __construct(
        private readonly ClientFactory $clientFactory,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param string $service Name used in logs and exceptions.
     * @param string|null $body Pre-encoded request body (JSON or NDJSON).
     * @param array<string, string> $headers
     * @param array{0: string, 1: string}|null $basicAuth
     * @param int[] $acceptedErrorStatuses Error statuses returned to the caller instead of thrown.
     * @throws ExternalServiceException
     */
    public function send(
        string $service,
        string $method,
        string $url,
        ?string $body,
        array $headers,
        int $timeout,
        ?array $basicAuth = null,
        array $acceptedErrorStatuses = []
    ): HttpResponse {
        $options = [
            'http_errors' => false,
            'timeout' => $timeout,
            'connect_timeout' => min($timeout, 10),
            'headers' => $headers + ['Accept' => 'application/json', 'Content-Type' => 'application/json'],
        ];
        if ($body !== null) {
            $options['body'] = $body;
        }
        if ($basicAuth !== null) {
            $options['auth'] = $basicAuth;
        }

        try {
            $response = $this->clientFactory->create()->request($method, $url, $options);
        } catch (TransferException $exception) {
            $this->logger->error(sprintf('[%s] %s %s failed: %s', $service, $method, $url, $exception->getMessage()));
            throw new ExternalServiceException($service, $exception);
        }

        $status = $response->getStatusCode();
        $raw = (string)$response->getBody();
        if ($status >= 400 && !in_array($status, $acceptedErrorStatuses, true)) {
            $this->logger->error(sprintf('[%s] %s %s returned %d: %s', $service, $method, $url, $status, mb_substr($raw, 0, 2000)));
            throw new ExternalServiceException($service);
        }

        return new HttpResponse($status, JsonCodec::decodeArray($raw));
    }
}
