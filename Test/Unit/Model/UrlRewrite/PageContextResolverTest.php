<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Test\Unit\Model\UrlRewrite;

use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;
use PHPUnit\Framework\TestCase;
use Perspective\AiCustomizationRequests\Model\UrlRewrite\PageContextResolver;

class PageContextResolverTest extends TestCase
{
    public function testResolvesStoreScopedRewriteFromCurrentPageUrl(): void
    {
        $store = $this->createMock(Store::class);
        $store->method('getBaseUrl')->willReturn('https://example.test/en/');
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->with(1)->willReturn($store);

        $rewrite = $this->createMock(UrlRewrite::class);
        $rewrite->method('getEntityType')->willReturn('product');
        $rewrite->method('getEntityId')->willReturn(42);
        $rewrite->method('getRequestPath')->willReturn('red-cup.html');
        $urlFinder = $this->createMock(UrlFinderInterface::class);
        $urlFinder->expects(self::once())
            ->method('findOneByData')
            ->with([
                UrlRewrite::REQUEST_PATH => 'red-cup.html',
                UrlRewrite::STORE_ID => 1,
            ])
            ->willReturn($rewrite);
        $product = $this->createMock(ProductInterface::class);
        $product->method('getSku')->willReturn('Red Cup');
        $product->method('getName')->willReturn('Red Cup');
        $productRepository = $this->createMock(ProductRepositoryInterface::class);
        $productRepository->expects(self::once())
            ->method('getById')
            ->with(42, false, 1, true)
            ->willReturn($product);

        self::assertSame([
            'entity_type' => 'product',
            'entity_id' => 42,
            'request_path' => 'red-cup.html',
            'product_sku' => 'Red Cup',
            'product_name' => 'Red Cup',
        ], (new PageContextResolver($urlFinder, $storeManager, $productRepository))->resolve('https://example.test/en/red-cup.html?source=chat', 1));
    }

    public function testRejectsUrlOutsideCurrentStorefrontHost(): void
    {
        $store = $this->createMock(Store::class);
        $store->method('getBaseUrl')->willReturn('https://example.test/');
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->with(1)->willReturn($store);
        $urlFinder = $this->createMock(UrlFinderInterface::class);
        $urlFinder->expects(self::never())->method('findOneByData');
        $productRepository = $this->createMock(ProductRepositoryInterface::class);

        self::assertNull((new PageContextResolver($urlFinder, $storeManager, $productRepository))->resolve('https://other.test/red-cup.html', 1));
    }
}
