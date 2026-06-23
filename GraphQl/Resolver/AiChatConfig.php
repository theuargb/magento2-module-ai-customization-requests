<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\GraphQl\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Perspective\AiCustomizationRequests\Model\Config\ModuleConfig;

class AiChatConfig implements ResolverInterface
{
    public function __construct(
        private readonly ModuleConfig $moduleConfig
    ) {
    }

    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null): array
    {
        return [
            'enabled' => $this->moduleConfig->isEnabled(),
            'widget_title' => $this->moduleConfig->getWidgetTitle(),
        ];
    }
}
