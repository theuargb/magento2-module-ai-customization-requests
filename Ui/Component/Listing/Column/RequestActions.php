<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Ui\Component\Listing\Columns\Column;

class RequestActions extends Column
{
    public function __construct(
        \Magento\Framework\View\Element\UiComponent\ContextInterface $context,
        \Magento\Framework\View\Element\UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $urlBuilder,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }

        foreach ($dataSource['data']['items'] as &$item) {
            if (!isset($item['request_id'])) {
                continue;
            }

            $item[$this->getData('name')]['edit'] = [
                'href' => $this->urlBuilder->getUrl(
                    'perspective_ai_request/request/edit',
                    ['request_id' => $item['request_id']]
                ),
                'label' => __('View'),
            ];
        }

        return $dataSource;
    }
}
