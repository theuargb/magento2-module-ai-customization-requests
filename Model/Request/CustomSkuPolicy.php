<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Request;

/**
 * The only definition of the custom product SKU: `<base-sku>-CUSTOM-<request_id>`, capped to 64 chars.
 */
class CustomSkuPolicy
{
    public function forRequest(CustomizationRequest $request): string
    {
        $sku = $request->getBaseProductSku() . '-CUSTOM-' . $request->getRequestId();

        return mb_substr($sku, 0, 64);
    }
}
