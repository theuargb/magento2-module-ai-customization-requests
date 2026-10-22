<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Perspective\AiCustomizationRequests\Model\Request\RequestStatus;

class RequestStatusOptions implements OptionSourceInterface
{
    /** @return list<array{value: string, label: \Magento\Framework\Phrase}> */
    public function toOptionArray(): array
    {
        return array_map(
            static fn(RequestStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
            RequestStatus::cases()
        );
    }
}
