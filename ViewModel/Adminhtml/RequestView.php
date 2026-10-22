<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\ViewModel\Adminhtml;

use Magento\Backend\Model\UrlInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Perspective\AiCustomizationRequests\Model\Request\CustomizationRequest;
use Perspective\AiCustomizationRequests\Model\Request\RequestHistory;
use Perspective\AiCustomizationRequests\Model\Request\RequestHistoryRepository;
use Perspective\AiCustomizationRequests\Model\Request\RequestRepository;
use Perspective\AiCustomizationRequests\Model\Request\RequestStatus;

/** Admin request page data; the request id comes from the current request, not the registry. */
class RequestView implements ArgumentInterface
{
    private ?CustomizationRequest $request = null;

    public function __construct(
        private readonly RequestInterface $httpRequest,
        private readonly RequestRepository $requestRepository,
        private readonly RequestHistoryRepository $historyRepository,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    /** @throws NoSuchEntityException */
    public function getRequest(): CustomizationRequest
    {
        return $this->request ??= $this->requestRepository->getById((int)$this->httpRequest->getParam('request_id'));
    }

    /** @return RequestHistory[] */
    public function getHistory(): array
    {
        return $this->historyRepository->getForRequest($this->getRequest()->getRequestId());
    }

    /** @return array<string, string> Requested changes flattened to label => readable value. */
    public function getRequestedChanges(): array
    {
        $rows = [];
        foreach ($this->getRequest()->getRequestedChanges() as $label => $value) {
            $rows[(string)$label] = is_scalar($value) ? (string)$value : $this->flatten($value);
        }

        return $rows;
    }

    public function isPending(): bool
    {
        return $this->getRequest()->getStatus() === RequestStatus::PendingReview;
    }

    public function canResendEmail(): bool
    {
        $status = $this->getRequest()->getStatus();

        return $status === RequestStatus::Approved || $status === RequestStatus::Declined;
    }

    public function getActionUrl(string $action): string
    {
        return $this->urlBuilder->getUrl(
            'perspective_ai_request/request_action/' . $action,
            ['request_id' => $this->getRequest()->getRequestId()]
        );
    }

    public function getCustomerUrl(): ?string
    {
        $customerId = $this->getRequest()->getCustomerId();

        return $customerId === null ? null : $this->urlBuilder->getUrl('customer/index/edit', ['id' => $customerId]);
    }

    public function getProductUrl(?int $productId): ?string
    {
        return $productId === null ? null : $this->urlBuilder->getUrl('catalog/product/edit', ['id' => $productId]);
    }

    private function flatten(mixed $value): string
    {
        if (!is_array($value)) {
            return '';
        }
        $parts = [];
        foreach ($value as $key => $item) {
            $text = is_scalar($item) ? (string)$item : $this->flatten($item);
            $parts[] = is_int($key) ? $text : $key . ': ' . $text;
        }

        return implode('; ', $parts);
    }
}
