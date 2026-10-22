<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\ViewModel;

use Magento\Framework\Serialize\Serializer\JsonHexTag;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Perspective\AiCustomizationRequests\Model\Config\AgentConfig;
use Perspective\AiCustomizationRequests\Model\Config\WidgetConfig;

/** Init payload of the storefront chat widget (cacheable: depends on the store view only). */
class ChatWidget implements ArgumentInterface
{
    public function __construct(
        private readonly WidgetConfig $widgetConfig,
        private readonly AgentConfig $agentConfig,
        private readonly StoreManagerInterface $storeManager,
        private readonly JsonHexTag $json
    ) {
    }

    public function getInitJson(): string
    {
        $store = $this->storeManager->getStore();
        $storeId = (int)$store->getId();
        $widget = $this->widgetConfig->get($storeId);
        $logoPath = $widget['logoPath'];
        unset($widget['logoPath']);

        return $this->json->serialize([
            'graphqlEndpoint' => $store->getBaseUrl(UrlInterface::URL_TYPE_LINK) . 'graphql',
            'storeCode' => $store->getCode(),
            'baseUrl' => $store->getBaseUrl(UrlInterface::URL_TYPE_LINK),
            'storageKey' => 'perspective_ai_chat:' . $store->getCode(),
            'maxMessageLength' => $this->agentConfig->getMaxMessageLength($storeId),
            'logoUrl' => $logoPath === ''
                ? ''
                : $store->getBaseUrl(UrlInterface::URL_TYPE_MEDIA) . 'perspective/ai_customization/' . $logoPath,
        ] + $widget);
    }
}
