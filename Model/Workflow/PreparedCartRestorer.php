<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Workflow;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\UrlInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Perspective\AiCustomizationRequests\Model\CustomizationRequest;
use Perspective\AiCustomizationRequests\Model\CustomizationRequestService;

class PreparedCartRestorer
{
    public function __construct(
        private readonly CustomizationRequestService $requestService,
        private readonly CheckoutSession $checkoutSession,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    public function restore(string $token): array
    {
        $request = $this->requestService->getByRestoreTokenHash(hash('sha256', $token));
        if (!$request || (string)$request->getData('status') !== CustomizationRequest::STATUS_APPROVED || !$request->getData('custom_product_id')) {
            throw new LocalizedException(__('This customization link is not available.'));
        }
        if (strtotime((string)$request->getData('restore_expires_at')) < time() || $request->getData('restore_consumed_at')) {
            throw new LocalizedException(__('This customization link has expired.'));
        }

        $quote = $this->checkoutSession->getQuote();
        $quote->setStoreId((int)$request->getData('store_id'));
        $product = $this->productRepository->getById((int)$request->getData('custom_product_id'), false, (int)$request->getData('store_id'), true);
        $result = $quote->addProduct($product, 1);
        if (is_string($result)) {
            throw new LocalizedException(__('%1', $result));
        }
        $quote->collectTotals();
        $this->cartRepository->save($quote);
        $request->setData('restore_consumed_at', gmdate('Y-m-d H:i:s'));
        $this->requestService->save($request);

        return ['success' => true, 'cart_url' => $this->urlBuilder->getUrl('checkout/cart'), 'masked_cart_id' => null];
    }
}
