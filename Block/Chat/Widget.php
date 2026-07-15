<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Block\Chat;

use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Template;
use Magento\Store\Model\StoreManagerInterface;
use Perspective\AiCustomizationRequests\Model\Config\ModuleConfig;

class Widget extends Template
{
    public function __construct(
        Template\Context $context,
        private readonly ModuleConfig $moduleConfig,
        private readonly StoreManagerInterface $storeManager,
        private readonly Json $json,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getInitConfigJson(): string
    {
        $widget = $this->moduleConfig->getWidgetConfig();
        $logo = $widget['logo'];
        $widget['logoUrl'] = $logo === ''
            ? ''
            : $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA) . $logo;

        return $this->json->serialize([
            'widget' => $widget,
            'graphqlEndpoint' => $this->getUrl('graphql'),
            'restoreToken' => $this->getRequest()->getParam('ai_quote'),
        ]);
    }
}
