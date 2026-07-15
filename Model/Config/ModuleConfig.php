<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;

class ModuleConfig
{
    private const XML_PATH_ENABLED = 'perspective_ai_customization/general/enabled';
    private const XML_PATH_WIDGET = 'perspective_ai_customization/widget';
    private const XML_PATH_WIDGET_WELCOME = 'perspective_ai_customization/widget_welcome';
    private const XML_PATH_WIDGET_MESSAGES = 'perspective_ai_customization/widget_messages';
    private const XML_PATH_WIDGET_APPEARANCE = 'perspective_ai_customization/widget_appearance';
    private const XML_PATH_OPENROUTER_API_KEY = 'perspective_ai_customization/openrouter/api_key';
    private const XML_PATH_OPENROUTER_SITE_URL = 'perspective_ai_customization/openrouter/site_url';
    private const XML_PATH_OPENROUTER_SITE_TITLE = 'perspective_ai_customization/openrouter/site_title';
    private const XML_PATH_CHAT_MODEL = 'perspective_ai_customization/agent/chat_model';
    private const XML_PATH_EMBEDDING_MODEL = 'perspective_ai_customization/agent/embedding_model';
    private const XML_PATH_MAX_ITERATIONS = 'perspective_ai_customization/agent/max_iterations';
    private const XML_PATH_HTTP_TIMEOUT = 'perspective_ai_customization/agent/http_timeout';
    private const XML_PATH_MAX_MESSAGE_LENGTH = 'perspective_ai_customization/agent/max_message_length';
    private const XML_PATH_SESSION_TTL_HOURS = 'perspective_ai_customization/agent/session_ttl_hours';
    private const XML_PATH_OS_HOST = 'perspective_ai_customization/opensearch/host';
    private const XML_PATH_OS_PORT = 'perspective_ai_customization/opensearch/port';
    private const XML_PATH_OS_INDEX = 'perspective_ai_customization/opensearch/index_name';
    private const XML_PATH_OS_USERNAME = 'perspective_ai_customization/opensearch/username';
    private const XML_PATH_OS_PASSWORD = 'perspective_ai_customization/opensearch/password';
    private const XML_PATH_OS_DIMS = 'perspective_ai_customization/opensearch/vector_dimensions';
    private const XML_PATH_OS_BATCH_SIZE = 'perspective_ai_customization/opensearch/batch_size';
    private const XML_PATH_EMAIL_ENABLED = 'perspective_ai_customization/email/enabled';
    private const XML_PATH_EMAIL_SENDER = 'perspective_ai_customization/email/sender_identity';
    private const XML_PATH_EMAIL_APPROVE_TEMPLATE = 'perspective_ai_customization/email/approve_template';
    private const XML_PATH_EMAIL_DECLINE_TEMPLATE = 'perspective_ai_customization/email/decline_template';

    private const XML_PATH_CATALOG_OS_HOST = 'catalog/search/opensearch_server_hostname';
    private const XML_PATH_CATALOG_OS_PORT = 'catalog/search/opensearch_server_port';
    private const XML_PATH_CATALOG_OS_AUTH_ENABLED = 'catalog/search/opensearch_enable_auth';
    private const XML_PATH_CATALOG_OS_USERNAME = 'catalog/search/opensearch_username';
    private const XML_PATH_CATALOG_OS_PASSWORD = 'catalog/search/opensearch_password';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface $encryptor
    ) {
    }

    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeFlag(self::XML_PATH_ENABLED, $storeId);
    }

    /**
     * Customer-facing widget settings. Values are read in the active storefront
     * scope so every store view can carry its own language and visual identity.
     *
     * @return array<string, mixed>
     */
    public function getWidgetConfig(?int $storeId = null): array
    {
        return [
            'title' => $this->getWidgetText('title', 'Style Assistant', $storeId),
            'icon' => $this->getWidgetText('icon', 'S', $storeId),
            'logo' => $this->getWidgetLogo($storeId),
            'copy' => [
                'connecting' => $this->getWidgetText('status_connecting', 'Connecting', $storeId),
                'ready' => $this->getWidgetText('status_ready', 'Ready to help', $storeId),
                'thinking' => $this->getWidgetText('status_thinking', 'Considering your request', $storeId),
                'unavailable' => $this->getWidgetText('status_unavailable', 'Unavailable', $storeId),
                'messageFailed' => $this->getWidgetText('status_message_failed', 'Message not sent', $storeId),
                'close' => $this->getWidgetText('close_label', 'Close assistant', $storeId),
                'clear' => $this->getWidgetText('clear_label', 'New chat', $storeId),
                'inputPlaceholder' => $this->getWidgetText('input_placeholder', 'Describe what you are looking for…', $storeId),
                'send' => $this->getWidgetText('send_label', 'Send', $storeId),
                'helper' => $this->getWidgetText('helper_text', 'Enter to send · Shift + Enter for a new line', $storeId),
                'welcomeEyebrow' => $this->getWidgetText('welcome_eyebrow', 'Your private fitting', $storeId),
                'welcomeTitle' => $this->getWidgetText('welcome_title', 'What should the right piece do for you?', $storeId),
                'welcomeCopy' => $this->getWidgetText('welcome_copy', 'Give me one useful detail. I will search the catalogue, compare options, or prepare a custom request.', $storeId),
                'user' => $this->getWidgetText('user_label', 'You', $storeId),
                'contextLabel' => $this->getWidgetText('context_label', 'Asking about', $storeId),
                'contextRemove' => $this->getWidgetText('context_remove_label', 'Remove', $storeId),
                'productLabel' => $this->getWidgetText('product_label', 'Catalogue match', $storeId),
                'productAsk' => $this->getWidgetText('product_ask_label', 'Ask about it', $storeId),
                'productView' => $this->getWidgetText('product_view_label', 'View', $storeId),
                'errorEyebrow' => $this->getWidgetText('error_eyebrow', 'Connection paused', $storeId),
                'errorTitle' => $this->getWidgetText('error_title', 'The assistant could not start.', $storeId),
                'errorCopy' => $this->getWidgetText('error_copy', 'Check the connection and try again.', $storeId),
                'errorRetry' => $this->getWidgetText('error_retry_label', 'Try again', $storeId),
                'messageSendError' => $this->getWidgetText('message_send_error', 'The message could not be sent.', $storeId),
                'prompts' => [
                    ['label' => $this->getWidgetText('prompt_one_label', 'Weather', $storeId), 'text' => $this->getWidgetText('prompt_one_text', 'I need shoes for a rainy day', $storeId)],
                    ['label' => $this->getWidgetText('prompt_two_label', 'Occasion', $storeId), 'text' => $this->getWidgetText('prompt_two_text', 'Help me choose something for an evening out', $storeId)],
                    ['label' => $this->getWidgetText('prompt_three_label', 'Custom detail', $storeId), 'text' => $this->getWidgetText('prompt_three_text', 'I like a product, but want to change it', $storeId)],
                ],
            ],
            'colors' => [
                'primary' => $this->getWidgetColor('primary_color', '#1463D9', $storeId),
                'primaryHover' => $this->getWidgetColor('primary_hover_color', '#0C4EAE', $storeId),
                'onPrimary' => $this->getWidgetColor('on_primary_color', '#FFFFFF', $storeId),
                'iconBackground' => $this->getWidgetColor('icon_background_color', '#FFFFFF', $storeId),
                'iconForeground' => $this->getWidgetColor('icon_foreground_color', '#1463D9', $storeId),
                'surface' => $this->getWidgetColor('surface_color', '#FFFFFF', $storeId),
                'canvas' => $this->getWidgetColor('canvas_color', '#F7F9FC', $storeId),
                'text' => $this->getWidgetColor('text_color', '#162033', $storeId),
                'muted' => $this->getWidgetColor('muted_color', '#667085', $storeId),
                'border' => $this->getWidgetColor('border_color', '#E5E9F0', $storeId),
            ],
        ];
    }

    public function getOpenRouterApiKey(?int $storeId = null): string
    {
        return $this->getSensitiveValue(self::XML_PATH_OPENROUTER_API_KEY, $storeId);
    }

    public function getOpenRouterSiteUrl(?int $storeId = null): string
    {
        return (string)$this->getValue(self::XML_PATH_OPENROUTER_SITE_URL, $storeId);
    }

    public function getOpenRouterSiteTitle(?int $storeId = null): string
    {
        return (string)$this->getValue(self::XML_PATH_OPENROUTER_SITE_TITLE, $storeId);
    }

    public function getChatModel(?int $storeId = null): string
    {
        return (string)$this->getValue(self::XML_PATH_CHAT_MODEL, $storeId);
    }

    public function getEmbeddingModel(?int $storeId = null): string
    {
        return (string)$this->getValue(self::XML_PATH_EMBEDDING_MODEL, $storeId);
    }

    public function getMaxIterations(?int $storeId = null): int
    {
        return max(1, (int)$this->getValue(self::XML_PATH_MAX_ITERATIONS, $storeId));
    }

    public function getHttpTimeout(?int $storeId = null): int
    {
        return max(1, (int)$this->getValue(self::XML_PATH_HTTP_TIMEOUT, $storeId));
    }

    public function getMaxMessageLength(?int $storeId = null): int
    {
        return min(10000, max(1, (int)$this->getValue(self::XML_PATH_MAX_MESSAGE_LENGTH, $storeId)));
    }

    public function getSessionTtlHours(?int $storeId = null): int
    {
        return min(720, max(1, (int)$this->getValue(self::XML_PATH_SESSION_TTL_HOURS, $storeId)));
    }

    public function getOpenSearchHost(?int $storeId = null): string
    {
        $host = (string)$this->getValue(self::XML_PATH_OS_HOST, $storeId);

        return $host !== '' ? $host : (string)$this->getValue(self::XML_PATH_CATALOG_OS_HOST, $storeId);
    }

    public function getOpenSearchPort(?int $storeId = null): int
    {
        $port = (string)$this->getValue(self::XML_PATH_OS_PORT, $storeId);

        return (int)($port !== '' ? $port : $this->getValue(self::XML_PATH_CATALOG_OS_PORT, $storeId));
    }

    public function getOpenSearchIndexName(?int $storeId = null): string
    {
        $name = (string)$this->getValue(self::XML_PATH_OS_INDEX, $storeId);
        return $storeId && $storeId > 0 ? $name . '_store_' . $storeId : $name;
    }

    public function getOpenSearchUsername(?int $storeId = null): string
    {
        $username = (string)$this->getValue(self::XML_PATH_OS_USERNAME, $storeId);

        if ($username !== '') {
            return $username;
        }

        return $this->isCatalogOpenSearchAuthEnabled($storeId)
            ? (string)$this->getValue(self::XML_PATH_CATALOG_OS_USERNAME, $storeId)
            : '';
    }

    public function getOpenSearchPassword(?int $storeId = null): string
    {
        $password = $this->getSensitiveValue(self::XML_PATH_OS_PASSWORD, $storeId);

        if ($password !== '') {
            return $password;
        }

        return $this->isCatalogOpenSearchAuthEnabled($storeId)
            ? $this->getSensitiveValue(self::XML_PATH_CATALOG_OS_PASSWORD, $storeId)
            : '';
    }

    public function getVectorDimensions(?int $storeId = null): int
    {
        return max(1, (int)$this->getValue(self::XML_PATH_OS_DIMS, $storeId));
    }

    public function getIndexBatchSize(?int $storeId = null): int
    {
        return max(1, (int)$this->getValue(self::XML_PATH_OS_BATCH_SIZE, $storeId));
    }

    public function canSendEmails(?int $storeId = null): bool
    {
        return $this->scopeFlag(self::XML_PATH_EMAIL_ENABLED, $storeId);
    }

    public function getSenderIdentity(?int $storeId = null): string
    {
        return (string)$this->getValue(self::XML_PATH_EMAIL_SENDER, $storeId);
    }

    public function getApproveTemplate(?int $storeId = null): string
    {
        return (string)$this->getValue(self::XML_PATH_EMAIL_APPROVE_TEMPLATE, $storeId);
    }

    public function getDeclineTemplate(?int $storeId = null): string
    {
        return (string)$this->getValue(self::XML_PATH_EMAIL_DECLINE_TEMPLATE, $storeId);
    }

    public function getOpenSearchBaseUrl(?int $storeId = null): string
    {
        $host = trim($this->getOpenSearchHost($storeId));
        if ($host === '') {
            return '';
        }

        if (preg_match('#^https?://#', $host) !== 1) {
            $scheme = $this->getOpenSearchPort($storeId) === 443 ? 'https://' : 'http://';
            $host = $scheme . $host;
        }

        $parsedPort = parse_url($host, PHP_URL_PORT);
        if ($parsedPort === null && $this->getOpenSearchPort($storeId) > 0) {
            $host .= ':' . $this->getOpenSearchPort($storeId);
        }

        return rtrim($host, '/');
    }

    private function isCatalogOpenSearchAuthEnabled(?int $storeId = null): bool
    {
        return $this->scopeFlag(self::XML_PATH_CATALOG_OS_AUTH_ENABLED, $storeId);
    }

    private function getValue(string $path, ?int $storeId = null): mixed
    {
        return $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);
    }

    private function getWidgetText(string $field, string $default, ?int $storeId): string
    {
        $value = trim((string)$this->getWidgetValue($field, $storeId));

        return $value !== '' ? $value : $default;
    }

    private function getWidgetColor(string $field, string $default, ?int $storeId): string
    {
        $value = trim((string)$this->getWidgetValue($field, $storeId));

        return preg_match('/^#[0-9a-f]{6}$/i', $value) === 1 ? strtoupper($value) : $default;
    }

    private function getWidgetLogo(?int $storeId): string
    {
        $value = ltrim(trim((string)$this->getWidgetValue('logo', $storeId)), '/');

        return preg_match('#^[a-z0-9][a-z0-9._/-]*$#i', $value) === 1 ? $value : '';
    }

    private function getWidgetValue(string $field, ?int $storeId): mixed
    {
        $path = match (true) {
            str_starts_with($field, 'welcome_'), str_starts_with($field, 'prompt_') => self::XML_PATH_WIDGET_WELCOME,
            str_starts_with($field, 'status_'), str_starts_with($field, 'error_'), $field === 'message_send_error' => self::XML_PATH_WIDGET_MESSAGES,
            str_ends_with($field, '_color') => self::XML_PATH_WIDGET_APPEARANCE,
            default => self::XML_PATH_WIDGET,
        };

        return $this->getValue($path . '/' . $field, $storeId);
    }

    private function getSensitiveValue(string $path, ?int $storeId = null): string
    {
        $value = (string)$this->getValue($path, $storeId);
        if ($value === '') {
            return '';
        }

        if (preg_match('/^\d+:\d+:/', $value) !== 1) {
            return $value;
        }

        try {
            $decrypted = $this->encryptor->decrypt($value);
        } catch (\Throwable) {
            return '';
        }

        if ($decrypted === '' || mb_detect_encoding($decrypted, 'UTF-8', true) === false) {
            return '';
        }

        return $decrypted;
    }

    private function scopeFlag(string $path, ?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag($path, ScopeInterface::SCOPE_STORE, $storeId);
    }
}
