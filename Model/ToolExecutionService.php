<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model;

use Magento\Framework\Serialize\Serializer\Json;
use Perspective\AiCustomizationRequests\Model\ResourceModel\ToolExecution as ToolExecutionResource;

class ToolExecutionService
{
    public function __construct(
        private readonly ToolExecutionFactory $toolExecutionFactory,
        private readonly ToolExecutionResource $toolExecutionResource,
        private readonly Json $json
    ) {
    }

    public function log(
        ?int $sessionId,
        ?int $requestId,
        string $toolName,
        string $status,
        ?array $arguments = null,
        ?array $result = null,
        ?string $errorMessage = null
    ): void {
        /** @var ToolExecution $toolExecution */
        $toolExecution = $this->toolExecutionFactory->create();
        $toolExecution->setData([
            'session_id' => $sessionId,
            'request_id' => $requestId,
            'tool_name' => $toolName,
            'status' => $status,
            'arguments_json' => $arguments ? $this->json->serialize($arguments) : null,
            'result_json' => $result ? $this->json->serialize($result) : null,
            'error_message' => $errorMessage,
        ]);
        $this->toolExecutionResource->save($toolExecution);
    }
}
