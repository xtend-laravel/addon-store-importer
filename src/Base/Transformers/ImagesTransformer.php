<?php

namespace XtendLunar\Addons\StoreImporter\Base\Transformers;

class ImagesTransformer extends Transformer
{
    public function transform(array $product):array
    {
        $product['product_images'] = collect($product['product_images'])->pluck('url');

        return $product;
    }
}
