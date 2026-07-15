<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\GraphQl\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Perspective\AiCustomizationRequests\Model\Workflow\PreparedCartRestorer;

class RestoreAiPreparedCart implements ResolverInterface
{
    public function __construct(private readonly PreparedCartRestorer $preparedCartRestorer)
    {
    }

    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null): array
    {
        return $this->preparedCartRestorer->restore((string)$args['token']);
    }
}
