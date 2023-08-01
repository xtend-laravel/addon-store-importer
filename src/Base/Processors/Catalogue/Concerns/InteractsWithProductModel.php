<?php

namespace XtendLunar\Addons\StoreImporter\Base\Processors\Catalogue\Concerns;

use Xtend\Extensions\Lunar\Core\Models\Product;

trait InteractsWithProductModel
{
    protected Product $productModel;

    public function setProductModel(Product $productModel): void
    {
        $this->productModel = $productModel;
    }

    public function getProductModel(): Product
    {
        return $this->productModel;
    }
}
