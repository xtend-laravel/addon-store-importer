<?php

namespace XtendLunar\Addons\StoreImporter\Base\Processors\Catalogue;

use Illuminate\Support\Collection;
use Lunar\Models\ProductType;
use Xtend\Extensions\Lunar\Core\Models\Product as ProductModel;
use XtendLunar\Addons\StoreImporter\Base\Processors\Catalogue\Concerns\InteractsWithProductModel;
use XtendLunar\Addons\StoreImporter\Base\Processors\Processor;
use XtendLunar\Addons\StoreImporter\Models\StoreImporterResourceModel;

class Product extends Processor
{
    use InteractsWithProductModel;

    public function process(Collection $product, StoreImporterResourceModel $resourceModel): void
    {
        $productModel = ProductModel::updateOrCreate([
            'sku' => $product->get('sku'),
        ], [
            'attribute_data' => $product->get('attribute_data'),
            'product_type_id' => $this->getDefaultProductTypeId(),
            'status' => $product->get('status') ?? 'draft',
        ]);

        $this->setProductModel($productModel);
        $product->put('productModel', $this->getProductModel());

        $resourceModel->model_type = $this->getProductModel()->getMorphClass();
        $resourceModel->model_id = $this->getProductModel()->getKey();
        $resourceModel->status = 'processing';
        $resourceModel->save();
    }

    protected function getDefaultProductTypeId(): int
    {
        return ProductType::query()->first()->id;
    }
}
