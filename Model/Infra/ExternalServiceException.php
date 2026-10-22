<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Infra;

use Magento\Framework\Exception\LocalizedException;

/** Upstream failure. The message is safe for shoppers; details are logged by JsonHttpClient. */
class ExternalServiceException extends LocalizedException
{
    public function __construct(private readonly string $service, ?\Exception $cause = null)
    {
        parent::__construct(__('The assistant is temporarily unavailable. Please try again later.'), $cause);
    }

    public function getService(): string
    {
        return $this->service;
    }
}
