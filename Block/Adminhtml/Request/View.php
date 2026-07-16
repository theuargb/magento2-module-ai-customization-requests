<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Block\Adminhtml\Request;

use Magento\Backend\Block\Template;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Json;
use Perspective\AiCustomizationRequests\Model\CustomizationRequest;
use Perspective\AiCustomizationRequests\Model\CustomizationRequestService;

class View extends Template
{
    public function __construct(
        Template\Context $context,
        private readonly Registry $registry,
        private readonly CustomizationRequestService $customizationRequestService,
        private readonly Json $json,
        array $data = [],
    ) {
        parent::__construct($context, $data);
    }

    public function getRequestModel(): CustomizationRequest
    {
        return $this->registry->registry("perspective_ai_current_request");
    }

    public function getHistory(): array
    {
        return $this->customizationRequestService->getHistory(
            $this->getRequestModel(),
        );
    }

    public function getApproveUrl(): string
    {
        return $this->getUrl("perspective_ai_request/request_action/approve", [
            "request_id" => $this->getRequestModel()->getId(),
        ]);
    }

    public function getDeclineUrl(): string
    {
        return $this->getUrl("perspective_ai_request/request_action/decline", [
            "request_id" => $this->getRequestModel()->getId(),
        ]);
    }

    public function getCustomerUrl(): ?string
    {
        $customerId = (int)$this->getRequestModel()->getData('customer_id');

        return $customerId > 0
            ? $this->getUrl('customer/index/edit', ['id' => $customerId])
            : null;
    }

    public function getBaseProductUrl(): ?string
    {
        $productId = (int)$this->getRequestModel()->getData('base_product_id');

        return $productId > 0
            ? $this->getUrl('catalog/product/edit', ['id' => $productId])
            : null;
    }

    public function getApprovedProductUrl(): ?string
    {
        $productId = (int)$this->getRequestModel()->getData('custom_product_id');

        return $productId > 0
            ? $this->getUrl('catalog/product/edit', ['id' => $productId])
            : null;
    }

    public function getFormKey(): string
    {
        return $this->formKey->getFormKey();
    }

    public function decodeJson(?string $value): array
    {
        if (!$value) {
            return [];
        }

        try {
            $decoded = $this->json->unserialize($value);
        } catch (\InvalidArgumentException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }
}
