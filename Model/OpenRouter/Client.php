<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\OpenRouter;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\Serialize\Serializer\Json;
use Perspective\AiCustomizationRequests\Model\Config\ModuleConfig;

class Client
{
    private const BASE_URL = 'https://openrouter.ai/api/v1';

    public function __construct(
        private readonly CurlFactory $curlFactory,
        private readonly ModuleConfig $moduleConfig,
        private readonly Json $json
    ) {
    }

    public function chatCompletion(array $messages, array $tools, string $model, ?int $storeId = null): array
    {
        $payload = [
            'model' => $model,
            'messages' => $messages,
        ];

        if ($tools) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = 'auto';
        }

        return $this->request('/chat/completions', $payload, $storeId);
    }

    public function embed(string $input, string $model, ?int $dimensions = null, ?int $storeId = null): array
    {
        $payload = [
            'model' => $model,
            'input' => $input,
        ];

        if ($dimensions) {
            $payload['dimensions'] = $dimensions;
        }

        $response = $this->request('/embeddings', $payload, $storeId);
        $embedding = $response['data'][0]['embedding'] ?? null;

        if (!is_array($embedding)) {
            throw new LocalizedException(__('OpenRouter embedding response is missing vector data.'));
        }

        return $embedding;
    }

    private function request(string $path, array $payload, ?int $storeId = null): array
    {
        $apiKey = trim($this->moduleConfig->getOpenRouterApiKey($storeId));
        if ($apiKey === '') {
            throw new LocalizedException(__('OpenRouter API key is not configured.'));
        }

        $curl = $this->curlFactory->create();
        $curl->setTimeout($this->moduleConfig->getHttpTimeout($storeId));
        $curl->addHeader('Accept', 'application/json');
        $curl->addHeader('Content-Type', 'application/json');
        $curl->addHeader('Authorization', 'Bearer ' . $apiKey);

        $siteUrl = trim($this->moduleConfig->getOpenRouterSiteUrl($storeId));
        if ($siteUrl !== '') {
            $curl->addHeader('HTTP-Referer', $siteUrl);
        }

        $siteTitle = trim($this->moduleConfig->getOpenRouterSiteTitle($storeId));
        if ($siteTitle !== '') {
            $curl->addHeader('X-OpenRouter-Title', $siteTitle);
        }

        $curl->post(self::BASE_URL . $path, $this->json->serialize($payload));

        $status = (int)$curl->getStatus();
        $body = $curl->getBody();

        try {
            $decoded = $this->json->unserialize($body ?: '{}');
        } catch (\InvalidArgumentException) {
            throw new LocalizedException(__('Unable to parse OpenRouter response.'));
        }

        if ($status >= 400) {
            $message = $decoded['error']['message'] ?? __('OpenRouter request failed.');
            throw new LocalizedException(__('%1', $message));
        }

        return is_array($decoded) ? $decoded : [];
    }
}
