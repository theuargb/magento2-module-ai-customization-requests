<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Workflow;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\GuestCartManagementInterface;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Perspective\AiCustomizationRequests\Model\CustomizationRequest;

class PreparedQuoteCreator
{
    public function __construct(
        private readonly GuestCartManagementInterface $guestCartManagement,
        private readonly MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly ProductRepositoryInterface $productRepository
    ) {
    }

    public function create(CustomizationRequest $request, int $productId): int
    {
        if ($request->getData('prepared_quote_id')) {
            return (int)$request->getData('prepared_quote_id');
        }

        $maskedCartId = $this->guestCartManagement->createEmptyCart();
        $quote = $this->cartRepository->get((int)$this->maskedQuoteIdToQuoteId->execute($maskedCartId));
        $quote->setStoreId((int)$request->getData('store_id'));
        $quote->setCustomerEmail((string)$request->getData('customer_email'));
        $product = $this->productRepository->getById($productId, false, (int)$request->getData('store_id'), true);
        $result = $quote->addProduct($product, 1);
        if (is_string($result)) {
            throw new LocalizedException(__('%1', $result));
        }
        $quote->collectTotals();
        $this->cartRepository->save($quote);

        return (int)$quote->getId();
    }
}
