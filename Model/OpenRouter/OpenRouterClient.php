<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\OpenRouter;
use Perspective\AiCustomizationRequests\Model\Config\OpenRouterConfig;
use Perspective\AiCustomizationRequests\Model\Infra\ExternalServiceException;
use Perspective\AiCustomizationRequests\Model\Infra\HttpResponse;
use Perspective\AiCustomizationRequests\Model\Infra\JsonCodec;
use Perspective\AiCustomizationRequests\Model\Infra\JsonHttpClient;

/**
 * OpenRouter chat completions and embeddings over JsonHttpClient (service name "openrouter").
 */
class OpenRouterClient
{
    private const BASE_URL = 'https://openrouter.ai/api/v1';

    public function __construct(
        private readonly JsonHttpClient $httpClient,
        private readonly OpenRouterConfig $config
    ) {
    }

    /**
     * @param list<array<string, mixed>> $messages OpenAI-format messages.
     * @param list<array<string, mixed>> $tools OpenAI-format function tools; omitted from payload when empty.
     * @return array<string, mixed> `choices[0].message` of the response (role, content, tool_calls?).
     * @throws ExternalServiceException Missing API key, transport error, or no choice in the response.
     */
    public function chat(array $messages, array $tools, string $model, int $storeId): array
    {
        $payload = ['model' => $model, 'messages' => $messages];
        if ($tools !== []) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = 'auto';
        }

        $response = $this->post('/chat/completions', $payload, $storeId);
        $message = $response->body['choices'][0]['message'] ?? null;

        if (!is_array($message)) {
            throw new ExternalServiceException('openrouter');
        }

        return $message;
    }

    /**
     * @param list<string> $inputs Batched in one request (OpenRouter accepts an array `input`).
     * @return list<list<float>> One vector per input, same order (sort `data` by `index`).
     * @throws ExternalServiceException
     */
    public function embed(array $inputs, int $dimensions, ?int $storeId): array
    {
        if ($inputs === []) {
            return [];
        }

        $payload = [
            'model' => $this->config->getEmbeddingModel($storeId),
            'input' => array_values($inputs),
            'dimensions' => $dimensions,
        ];

        $response = $this->post('/embeddings', $payload, $storeId);
        $data = $response->body['data'] ?? [];
        if (!is_array($data) || count($data) !== count($inputs)) {
            throw new ExternalServiceException('openrouter');
        }

        usort($data, static fn(array $a, array $b): int => (int)($a['index'] ?? 0) <=> (int)($b['index'] ?? 0));

        $vectors = [];
        foreach ($data as $item) {
            $vector = $item['embedding'] ?? null;
            if (!is_array($vector) || count($vector) !== $dimensions) {
                throw new ExternalServiceException('openrouter');
            }
            $vectors[] = array_map(static fn(mixed $component): float => (float)$component, array_values($vector));
        }

        return $vectors;
    }

    /**
     * @param array<string, mixed> $payload
     * @throws ExternalServiceException
     */
    private function post(string $path, array $payload, ?int $storeId): HttpResponse
    {
        $apiKey = $this->config->getApiKey($storeId);
        if ($apiKey === '') {
            throw new ExternalServiceException('openrouter');
        }

        $headers = ['Authorization' => 'Bearer ' . $apiKey];
        $siteUrl = $this->config->getSiteUrl($storeId);
        if ($siteUrl !== '') {
            $headers['HTTP-Referer'] = $siteUrl;
        }
        $siteTitle = $this->config->getSiteTitle($storeId);
        if ($siteTitle !== '') {
            $headers['X-Title'] = $siteTitle;
        }

        return $this->httpClient->send(
            'openrouter',
            'POST',
            self::BASE_URL . $path,
            JsonCodec::encode($payload),
            $headers,
            $this->config->getHttpTimeout($storeId)
        );
    }
}
