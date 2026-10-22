<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\GraphQl;

use Magento\GraphQl\Model\Query\ContextInterface;

/** Store and customer of the current GraphQL request (customer comes from token or Luma session). */
class RequestScope
{
    public function storeId(ContextInterface $context): int
    {
        return (int)$context->getExtensionAttributes()->getStore()->getId();
    }

    public function customerId(ContextInterface $context): ?int
    {
        return $context->getExtensionAttributes()->getIsCustomer() ? (int)$context->getUserId() : null;
    }
}
