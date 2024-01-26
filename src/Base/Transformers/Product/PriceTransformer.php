<?php

namespace XtendLunar\Addons\StoreImporter\Base\Transformers\Product;

use XtendLunar\Addons\StoreImporter\Base\Transformers\Transformer;

class PriceTransformer extends Transformer
{
    public function transform(array $product):array
    {
        $product['product_prices'] = collect([
            'default' => preg_replace('/[^0-9]/', '', $product['product_price_default'] * 100),
        ]);

        return $product;
    }
}
