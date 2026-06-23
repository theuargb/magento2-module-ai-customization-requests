<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Workflow;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\GuestCartManagementInterface;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Magento\Quote\Model\QuoteIdToMaskedQuoteIdInterface;
use Perspective\AiCustomizationRequests\Model\CustomizationRequest;
use Perspective\AiCustomizationRequests\Model\CustomizationRequestService;

class QuoteDraftService
{
    public function __construct(
        private readonly GuestCartManagementInterface $guestCartManagement,
        private readonly MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly QuoteIdToMaskedQuoteIdInterface $quoteIdToMaskedQuoteId,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly CustomizationRequestService $customizationRequestService,
        private readonly ProductDraftService $productDraftService
    ) {
    }

    public function createForRequest(CustomizationRequest $request): CustomizationRequest
    {
        if ($request->getData('draft_quote_id')) {
            return $request;
        }

        if (!$request->getData('customer_email')) {
            throw new LocalizedException(__('Customer email is required before creating a draft quote.'));
        }

        if (!$request->getData('draft_product_id')) {
            $request = $this->productDraftService->createForRequest($request);
        }

        $maskedCartId = $this->guestCartManagement->createEmptyCart();
        $quoteId = (int)$this->maskedQuoteIdToQuoteId->execute($maskedCartId);
        $quote = $this->cartRepository->get($quoteId);
        $storeId = (int)$request->getData('store_id');
        $product = $this->productRepository->get((string)$request->getData('draft_product_sku'), false, $storeId ?: 0, true);

        $quote->setStoreId($storeId);
        $quote->setCustomerEmail((string)$request->getData('customer_email'));
        $quote->setCustomerIsGuest(true);
        $quote->setCheckoutMethod(\Magento\Quote\Api\CartManagementInterface::METHOD_GUEST);
        $quote->addProduct($product, 1);
        $quote->collectTotals();
        $this->cartRepository->save($quote);

        $request->addData([
            'draft_quote_id' => $quoteId,
            'masked_quote_id' => $this->quoteIdToMaskedQuoteId->execute($quoteId),
            'status' => CustomizationRequest::STATUS_QUOTE_DRAFT_CREATED,
        ]);
        $this->customizationRequestService->ensureRestoreToken($request);
        $this->customizationRequestService->save($request);
        $this->customizationRequestService->addHistory(
            $request,
            'agent',
            0,
            'draft_quote_created',
            $request->getData('masked_quote_id')
        );

        return $request;
    }
}
