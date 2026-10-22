<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\ViewModel;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Serialize\Serializer\JsonHexTag;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Perspective\AiCustomizationRequests\Model\Config\WidgetConfig;

/** Init payload of the cart-restore page; the token comes from the URL and is never cached by FPC. */
class RestoreCart implements ArgumentInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly WidgetConfig $widgetConfig,
        private readonly StoreManagerInterface $storeManager,
        private readonly JsonHexTag $json
    ) {
    }

    public function getInitJson(): string
    {
        $store = $this->storeManager->getStore();
        $copy = $this->widgetConfig->get((int)$store->getId())['copy'];

        return $this->json->serialize([
            'graphqlEndpoint' => $store->getBaseUrl(UrlInterface::URL_TYPE_LINK) . 'graphql',
            'storeCode' => $store->getCode(),
            'token' => trim((string)$this->request->getParam('token')),
            'copy' => ['progress' => $copy['restoreProgress'], 'error' => $copy['restoreError']],
        ]);
    }
}
