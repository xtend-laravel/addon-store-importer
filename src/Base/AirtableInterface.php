<?php

namespace XtendLunar\Addons\StoreImporter\Base;

use Illuminate\Support\Collection;
use Lunar\Models\Product;

interface AirtableInterface
{
    public function products(): array;

    public function modifyProduct(Product $product, string $productId): void;

    public function removeProduct(Product $product, string $productId): void;

    public function createProduct(Product $product): void;

    public function removeAll(Collection $productIds): void;
}
