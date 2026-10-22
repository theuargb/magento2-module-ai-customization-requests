<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Request;

use Magento\Framework\App\Area;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\MailException;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Store\Model\StoreManagerInterface;
use Perspective\AiCustomizationRequests\Model\Config\EmailConfig;
use Psr\Log\LoggerInterface;

/**
 * Customer decision emails. Returns false (without sending) when emails are disabled for the store.
 */
class CustomerNotifier
{
    public function __construct(
        private readonly TransportBuilder $transportBuilder,
        private readonly StateInterface $inlineTranslation,
        private readonly EmailConfig $emailConfig,
        private readonly StoreManagerInterface $storeManager,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Template vars: request_name, restore_url (`ai-customization/restore/index/token/<t>/` on the request store).
     * @throws LocalizedException Sending failed; details are logged.
     */
    public function sendApproved(CustomizationRequest $request, string $restoreToken): bool
    {
        if (!$this->emailConfig->isEnabled($request->getStoreId())) {
            return false;
        }

        $storeId = $request->getStoreId();
        /** @var \Magento\Store\Model\Store $store */
        $store = $this->storeManager->getStore($storeId);
        $restoreUrl = $store->getBaseUrl() . 'ai-customization/restore/index/token/' . urlencode($restoreToken) . '/';

        return $this->send(
            $request,
            $this->emailConfig->getApproveTemplate($storeId),
            [
                'request_name' => $request->getTitle(),
                'restore_url' => $restoreUrl,
            ]
        );
    }

    /**
     * Template vars: request_name, decline_reason.
     * @throws LocalizedException Sending failed; details are logged.
     */
    public function sendDeclined(CustomizationRequest $request): bool
    {
        if (!$this->emailConfig->isEnabled($request->getStoreId())) {
            return false;
        }

        $storeId = $request->getStoreId();

        return $this->send(
            $request,
            $this->emailConfig->getDeclineTemplate($storeId),
            [
                'request_name' => $request->getTitle(),
                'decline_reason' => (string)$request->getDecisionNote(),
            ]
        );
    }

    /**
     * @param array<string, mixed> $templateVars
     */
    private function send(CustomizationRequest $request, string $templateId, array $templateVars): bool
    {
        $storeId = $request->getStoreId();
        $transport = $this->transportBuilder
            ->setTemplateIdentifier($templateId)
            ->setTemplateOptions(['area' => Area::AREA_FRONTEND, 'store' => $storeId])
            ->setTemplateVars($templateVars)
            ->setFromByScope($this->emailConfig->getSenderIdentity($storeId), $storeId)
            ->addTo($request->getCustomerEmail())
            ->getTransport();

        $this->inlineTranslation->suspend();
        try {
            $transport->sendMessage();
        } catch (MailException $exception) {
            $this->logger->error('Customization request email failed: ' . $exception->getMessage());
            throw new LocalizedException(
                __('The customer email could not be sent. Use "Resend email" to try again.'),
                $exception
            );
        } finally {
            $this->inlineTranslation->resume();
        }

        return true;
    }
}
