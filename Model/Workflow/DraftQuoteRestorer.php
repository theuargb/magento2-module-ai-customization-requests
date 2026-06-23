<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Workflow;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\UrlInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Perspective\AiCustomizationRequests\Model\CustomizationRequest;
use Perspective\AiCustomizationRequests\Model\CustomizationRequestService;

class DraftQuoteRestorer
{
    public function __construct(
        private readonly CustomizationRequestService $customizationRequestService,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly CheckoutSession $checkoutSession,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    public function restore(string $token): array
    {
        $request = $this->customizationRequestService->getByRestoreToken($token);
        if (!$request || !$request->getId()) {
            throw new LocalizedException(__('Draft quote link is invalid.'));
        }

        if (!(int)$request->getData('draft_quote_id')) {
            throw new LocalizedException(__('This request does not have a draft quote yet.'));
        }

        $quote = $this->cartRepository->get((int)$request->getData('draft_quote_id'));
        $quote->setIsActive(true);
        $this->cartRepository->save($quote);
        $this->checkoutSession->replaceQuote($quote);

        return [
            'success' => true,
            'cart_url' => $this->urlBuilder->getUrl('checkout/cart'),
            'masked_cart_id' => (string)$request->getData('masked_quote_id'),
        ];
    }
}
