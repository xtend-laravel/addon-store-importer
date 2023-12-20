<?php

namespace XtendLunar\Addons\StoreImporter\Base;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Lunar\Models\Product;
use XtendLunar\Addons\StoreImporter\Airtable\Requests\Products\Data\CreateProductData;

class AirtableManager implements AirtableInterface
{
    public function __construct(
        private AirtableApiService $merchantService,
    ) {
    }

    public function products(): array
    {
        return $this->merchantService->fetchProducts();
    }

    public function createProduct(Product $product): void
    {
        $payload = $this->preparePayload($product);

        $this->merchantService->createProduct($payload);
    }

    public function modifyProduct(Product $product, string $productId): void
    {
        $payload = Arr::except($this->preparePayload($product), [
            'offerId',
            'targetCountry',
            'contentLanguage',
            'channel',
        ]);

        $this->merchantService->updateProduct($payload, $productId);
    }

    public function removeAll(Collection $productIds): void
    {
        collect($productIds)->each(
            fn(string $productId) => $this->merchantService->deleteProduct($productId),
        );
    }

    public function removeProduct(Product $product, string $productId): void
    {
        $this->merchantService->deleteProduct($productId);
    }

    protected function preparePayload(Product $product): array
    {
        return CreateProductData::from(
            AirtableMapping::make($product)->map(),
        )->toArray();
    }
}
