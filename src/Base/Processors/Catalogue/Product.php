<?php

namespace XtendLunar\Addons\StoreImporter\Base\Processors\Catalogue;

use Illuminate\Support\Collection;
use Lunar\Models\Language;
use Lunar\Models\ProductType;
use Xtend\Extensions\Lunar\Core\Models\Product as ProductModel;
use XtendLunar\Addons\StoreImporter\Base\Processors\Catalogue\Concerns\InteractsWithProductModel;
use XtendLunar\Addons\StoreImporter\Base\Processors\Processor;
use XtendLunar\Addons\StoreImporter\Models\StoreImporterResourceModel;

class Product extends Processor
{
    use InteractsWithProductModel;

    public function process(Collection $product, ?StoreImporterResourceModel $resourceModel = null): void
    {
        /** @var ProductModel $productModel */
        $productModel = ProductModel::updateOrCreate([
            'sku' => strtolower($product->get('product_sku')) ?? strtolower($product->get('sku')),
        ], [
            'attribute_data' => $product->get('attribute_data'),
            'product_type_id' => $this->getDefaultProductTypeId(),
            'status' => 'published',
        ]);

        $productModel->urls()->delete();
        $productName = $product->get('attribute_data')['name']->getValue();

        $productName->each(function ($value, $isoCode) use ($productModel, $product) {
            $languageId = Language::query()->firstWhere('code', $isoCode)->id;
            $productModel->urls()->create([
                'default' => $languageId === Language::getDefault()->id,
                'language_id' => $languageId,
                'slug' => $this->slug($value).'-'.strtolower($product->get('product_sku')) ?? strtolower($product->get('sku')),
            ]);
        });

        $this->setProductModel($productModel);
        $product->put('productModel', $this->getProductModel());

        if ($resourceModel) {
            $resourceModel->model_type = $this->getProductModel()->getMorphClass();
            $resourceModel->model_id = $this->getProductModel()->getKey();
            $resourceModel->status = 'processing';
            $resourceModel->save();
        }
    }

    protected function getDefaultProductTypeId(): int
    {
        return ProductType::query()->first()->id;
    }

    public function slug($string, $separator = '-')
    {
        if (is_null($string)) {
            return "";
        }

        $string = trim($string);

        $string = mb_strtolower($string, "UTF-8");;

        $string = preg_replace("/[^a-z0-9_\sءاأإآؤئبتثجحخدذرزسشصضطظعغفقكلمنهويةى]#u/", "", $string);

        $string = preg_replace("/[\s-]+/", " ", $string);

        $string = preg_replace("/[\s_]/", $separator, $string);

        return $string;
    }
}
