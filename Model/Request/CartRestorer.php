<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Request;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Perspective\AiCustomizationRequests\Model\Infra\TokenService;

/**
 * Prepared cart option A: the restore token adds the approved custom product to the visitor's own
 * cart (checkout session quote; customer session works in the Luma GraphQL area) and is consumed.
 */
class CartRestorer
{
    public function __construct(
        private readonly RequestRepository $requestRepository,
        private readonly RequestHistoryRepository $historyRepository,
        private readonly TokenService $tokenService,
        private readonly CheckoutSession $checkoutSession,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    /**
     * @return string Absolute cart page URL of the request store.
     * @throws LocalizedException Unknown, expired, consumed, or other-store token; add-to-cart failure.
     */
    public function restore(string $token, int $storeId): string
    {
        $request = $this->requestRepository->findByRestoreTokenHash($this->tokenService->hash($token));
        if ($request === null || $request->getStoreId() !== $storeId || !$request->isRestorable()) {
            throw new LocalizedException(__('This customization link is no longer available.'));
        }

        try {
            $customProduct = $this->productRepository->getById((int)$request->getCustomProductId());
        } catch (NoSuchEntityException $exception) {
            throw new LocalizedException(__('This customization link is no longer available.'), $exception);
        }
        if (!$customProduct instanceof Product) {
            throw new LocalizedException(__('This customization link is no longer available.'));
        }

        $quote = $this->checkoutSession->getQuote();
        $result = $quote->addProduct($customProduct, 1);
        if (is_string($result)) {
            throw new LocalizedException(__($result));
        }

        $quote->collectTotals();
        $this->cartRepository->save($quote);
        $this->checkoutSession->setQuoteId($quote->getId());

        $request->markRestoreConsumed();
        $this->requestRepository->save($request);
        $this->historyRepository->add(
            $request,
            RequestHistory::ACTOR_CUSTOMER,
            $quote->getCustomerIsGuest() ? null : (int)$quote->getCustomerId(),
            'restored'
        );

        return $this->urlBuilder->getUrl('checkout/cart');
    }
}
