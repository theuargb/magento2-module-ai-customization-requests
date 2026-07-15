<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Email;

use Magento\Framework\App\Area;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Perspective\AiCustomizationRequests\Model\Config\ModuleConfig;
use Perspective\AiCustomizationRequests\Model\CustomizationRequest;

class CustomerNotifier
{
    public function __construct(
        private readonly TransportBuilder $transportBuilder,
        private readonly StateInterface $inlineTranslation,
        private readonly ModuleConfig $moduleConfig,
        private readonly UrlInterface $urlBuilder,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function sendApproved(CustomizationRequest $request, string $restoreToken): void
    {
        $this->send($request, $this->moduleConfig->getApproveTemplate((int)$request->getData('store_id')), [
            'request_uid' => (string)$request->getData('request_uid'),
            'restore_url' => $this->buildRestoreUrl($request, $restoreToken),
        ]);
    }

    public function sendDeclined(CustomizationRequest $request): void
    {
        $this->send($request, $this->moduleConfig->getDeclineTemplate((int)$request->getData('store_id')), [
            'request_uid' => (string)$request->getData('request_uid'),
            'decline_reason' => (string)$request->getData('decline_reason'),
        ]);
    }

    private function send(CustomizationRequest $request, string $templateIdentifier, array $vars): void
    {
        if (!$this->moduleConfig->canSendEmails((int)$request->getData('store_id'))) {
            return;
        }

        $email = (string)$request->getData('customer_email');
        if ($email === '') {
            throw new LocalizedException(__('Customer email is not set for this request.'));
        }

        $this->inlineTranslation->suspend();
        try {
            $transport = $this->transportBuilder
                ->setTemplateIdentifier($templateIdentifier)
                ->setTemplateOptions([
                    'area' => Area::AREA_FRONTEND,
                    'store' => (int)$request->getData('store_id'),
                ])
                ->setTemplateVars($vars)
                ->setFromByScope($this->moduleConfig->getSenderIdentity((int)$request->getData('store_id')))
                ->addTo($email)
                ->getTransport();
            $transport->sendMessage();
        } finally {
            $this->inlineTranslation->resume();
        }
    }

    private function buildRestoreUrl(CustomizationRequest $request, string $restoreToken): string
    {
        return $this->urlBuilder->getUrl('', [
            '_scope' => $request->getData('store_id') ?: $this->storeManager->getDefaultStoreView()->getId(),
            '_query' => ['ai_quote' => $restoreToken],
        ]);
    }
}
