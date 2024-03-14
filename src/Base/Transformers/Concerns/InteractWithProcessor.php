<?php

namespace XtendLunar\Addons\StoreImporter\Base\Transformers\Concerns;

use XtendLunar\Addons\StoreImporter\Base\Processors;
use XtendLunar\Addons\StoreImporter\Concerns\InteractsWithPipeline;

trait InteractWithProcessor
{
    use InteractsWithPipeline;

    protected function syncProduct(array $product, $withImages = true): mixed
    {
        $processors = [
            Processors\Catalogue\Product::class,
            Processors\Catalogue\Collections::class,
            Processors\Catalogue\ProductOptions::class,
            Processors\Catalogue\ProductFeatures::class,
            Processors\Catalogue\ProductVariants::class,
        ];

        if ($withImages) {
            $processors[] = Processors\Catalogue\ProductImages::class;
        }

        return $this->prepareThroughPipeline(
            passable: [
                'product' => collect($product),
            ],
            pipes: $processors,
        );
    }
}
