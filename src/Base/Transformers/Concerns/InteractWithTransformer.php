<?php

namespace XtendLunar\Addons\StoreImporter\Base\Transformers\Concerns;

use XtendLunar\Addons\StoreImporter\Base\Transformers;
use XtendLunar\Addons\StoreImporter\Concerns\InteractsWithPipeline;

trait InteractWithTransformer
{
    use InteractsWithPipeline;

    protected function transformProduct(array $product): mixed
    {
        return $this->prepareThroughPipeline(
            passable: [
                'product' => $product,
            ],
            pipes: [
                Transformers\Product\FieldMapTransformer::class,
                Transformers\Product\AttributeDataTransformer::class,
                Transformers\Product\PriceTransformer::class,
                Transformers\Product\FeaturesTransformer::class,
            ],
        );
    }

    protected function transformVariant(array $productVariant): mixed
    {
        // @todo We only need certain fields for the product variant so better to use custom variant map.
        $productVariant = $this->transformProduct($productVariant['fields']);

        return $this->prepareThroughPipeline(
            passable: $productVariant,
            pipes: [
                Transformers\Variant\ColorTransformer::class,
                Transformers\Variant\SizeStockTransformer::class,
                Transformers\Variant\ImagesTransformer::class,
            ],
        );
    }
}
