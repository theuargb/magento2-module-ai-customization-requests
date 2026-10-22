<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Controller\Adminhtml\Request\Action;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Perspective\AiCustomizationRequests\Model\Request\CustomizationRequest;
use Perspective\AiCustomizationRequests\Model\Request\RequestRepository;
use Psr\Log\LoggerInterface;
use Throwable;

abstract class AbstractRequestAction extends Action implements HttpPostActionInterface
{
    public function __construct(
        Context $context,
        private readonly RequestRepository $requestRepository,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    abstract protected function handle(CustomizationRequest $request, int $adminUserId): void;

    public function execute(): Redirect
    {
        $requestId = (int)$this->getRequest()->getParam('request_id');

        try {
            $request = $this->requestRepository->getById($requestId);
            $this->handle($request, (int)$this->_auth->getUser()->getId());
        } catch (NoSuchEntityException|LocalizedException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        } catch (Throwable $exception) {
            $this->logger->error('AI request action failed: ' . $exception->getMessage(), [
                'request_id' => $requestId,
                'exception' => $exception,
            ]);
            $this->messageManager->addErrorMessage(
                __('An unexpected error occurred. The action was not completed.')
            );
        }

        return $this->resultRedirectFactory->create()->setPath(
            'perspective_ai_request/request/edit',
            ['request_id' => $requestId]
        );
    }
}
