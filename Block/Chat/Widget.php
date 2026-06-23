<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Block\Chat;

use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Template;
use Perspective\AiCustomizationRequests\Model\Config\ModuleConfig;

class Widget extends Template
{
    public function __construct(
        Template\Context $context,
        private readonly ModuleConfig $moduleConfig,
        private readonly Json $json,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getInitConfigJson(): string
    {
        return $this->json->serialize([
            'widgetTitle' => $this->moduleConfig->getWidgetTitle(),
            'graphqlEndpoint' => $this->getUrl('graphql'),
            'restoreToken' => $this->getRequest()->getParam('ai_quote'),
        ]);
    }
}
