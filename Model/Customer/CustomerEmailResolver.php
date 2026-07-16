<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Customer;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;

class CustomerEmailResolver
{
    public function __construct(
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly CheckoutSession $checkoutSession,
        private readonly CartRepositoryInterface $cartRepository
    ) {
    }

    public function get(?int $customerId, int $storeId): ?string
    {
        if ($customerId) {
            return (string)$this->customerRepository->getById($customerId)->getEmail();
        }

        $quote = $this->getGuestQuote($storeId);
        return $quote ? ((string)$quote->getCustomerEmail() ?: null) : null;
    }

    public function setGuestEmail(string $email, int $storeId): void
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new LocalizedException(__('A valid email address is required.'));
        }

        $quote = $this->getGuestQuote($storeId, true);
        if (!$quote) {
            throw new LocalizedException(__('An active guest cart is required to save an email address.'));
        }
        if ((string)$quote->getCustomerEmail() === $email) {
            return;
        }

        $quote->setCustomerEmail($email);
        $this->cartRepository->save($quote);
        $this->checkoutSession->setQuoteId((int)$quote->getId());
    }

    private function getGuestQuote(int $storeId, bool $create = false): ?\Magento\Quote\Api\Data\CartInterface
    {
        $quoteId = (int)$this->checkoutSession->getQuoteId();
        if ($quoteId > 0) {
            try {
                $quote = $this->cartRepository->getActive($quoteId);
            } catch (NoSuchEntityException) {
                return null;
            }
        } elseif ($create) {
            $quote = $this->checkoutSession->getQuote();
        } else {
            return null;
        }

        if ((int)$quote->getStoreId() !== $storeId || !(bool)$quote->getCustomerIsGuest()) {
            return null;
        }

        return $quote;
    }
}
