<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Chat;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Validator\EmailAddress;

/**
 * Sole owner of the session contact email: customer account email for logged-in shoppers,
 * the validated `guest_email` input for guests. Nothing else writes or derives it.
 */
class ChatIdentity
{
    public function __construct(
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly EmailAddress $emailValidator
    ) {
    }

    /**
     * Sets the session email (caller saves the session). An existing email is never cleared;
     * the customer account email wins over a previously stored guest email.
     *
     * @throws LocalizedException Invalid guest email.
     */
    public function attach(ChatSession $session, ?int $customerId, ?string $guestEmail): void
    {
        if ($customerId !== null) {
            $email = trim((string)$this->customerRepository->getById($customerId)->getEmail());
            if ($email !== '' && $email !== $session->getCustomerEmail()) {
                $session->setCustomerEmail($email);
            }

            return;
        }

        if ($guestEmail !== null) {
            $guestEmail = trim($guestEmail);
            if ($guestEmail !== '') {
                if (!$this->emailValidator->isValid($guestEmail)) {
                    throw new LocalizedException(__('Please enter a valid email address.'));
                }
                $session->setCustomerEmail($guestEmail);
            }
        }
    }

    public function isEmailRequired(ChatSession $session): bool
    {
        return $session->getCustomerEmail() === null;
    }
}
