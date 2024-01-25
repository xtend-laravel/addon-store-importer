<?php

namespace XtendLunar\Addons\StoreImporter\Base\Transformers\Variant;

use XtendLunar\Addons\StoreImporter\Base\Transformers\Transformer;

class ImagesTransformer extends Transformer
{
    public function transform(array $productVariant): array
    {
        $productVariant['images'] = collect($productVariant['product_images']);

        return $productVariant;
    }
}
