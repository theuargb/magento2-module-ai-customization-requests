<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Agent;

use Perspective\AiCustomizationRequests\Model\ChatSession;
use Perspective\AiCustomizationRequests\Model\CustomizationRequest;

class AgentContext
{
    public function __construct(
        private readonly ChatSession $session,
        private readonly ?int $customerId,
        private ?string $customerEmail = null,
        private ?CustomizationRequest $request = null
    ) {
    }

    public function getSession(): ChatSession
    {
        return $this->session;
    }

    public function getCustomerId(): ?int
    {
        return $this->customerId;
    }

    public function getCustomerEmail(): ?string
    {
        return $this->customerEmail;
    }

    public function setCustomerEmail(?string $customerEmail): void
    {
        $this->customerEmail = $customerEmail;
    }

    public function getRequest(): ?CustomizationRequest
    {
        return $this->request;
    }

    public function setRequest(?CustomizationRequest $request): void
    {
        $this->request = $request;
    }
}
