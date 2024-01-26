<?php

namespace XtendLunar\Addons\StoreImporter\Base\Transformers\Concerns;

use XtendLunar\Addons\StoreImporter\Base\Processors;
use XtendLunar\Addons\StoreImporter\Concerns\InteractsWithPipeline;

trait InteractWithProcessor
{
    use InteractsWithPipeline;

    protected function syncProduct(array $product): mixed
    {
        return $this->prepareThroughPipeline(
            passable: [
                'product' => collect($product),
            ],
            pipes: [
                Processors\Catalogue\Product::class,
                Processors\Catalogue\Collections::class,
                Processors\Catalogue\ProductOptions::class,
                Processors\Catalogue\ProductFeatures::class,
                Processors\Catalogue\ProductVariants::class,
                Processors\Catalogue\ProductImages::class,
            ],
        );
    }
}
