<?php

namespace XtendLunar\Addons\StoreImporter\Base\Processors\Catalogue;

use Illuminate\Support\Collection;
use Lunar\Models\Currency;
use Lunar\Models\TaxClass;
use XtendLunar\Addons\StoreImporter\Base\Processors\Processor;
use XtendLunar\Addons\StoreImporter\Models\StoreImporterResourceModel;
use XtendLunar\Features\ProductFeatures\Models\ProductFeature;
use XtendLunar\Features\ProductFeatures\Models\ProductFeatureValue;

class ProductFeatureValueSave extends Processor
{
    protected TaxClass $taxClass;

    protected Currency $currency;

    public function __construct()
    {
        $this->taxClass = TaxClass::getDefault();
        $this->currency = Currency::getDefault();
    }

    public function process(Collection $product, ?StoreImporterResourceModel $resourceModel = null): mixed
    {
        /** @var \Lunar\Models\Product $productModel */
        $productModel = $product->get('productModel');

        if (! $product->has('features') || (! ProductFeature::count() && ! ProductFeatureValue::count())) {
            return $product;
        }

        if ($features = $product->get('features')) {
            /** @var \Illuminate\Database\Eloquent\Relations\BelongsToMany $relation */
            $relation = $productModel->featureValues();
            $relation->sync($this->lookupFeatureValueIds($features));
        }

        return [$product];
    }

    protected function lookupFeatureValueIds(array $features): Collection
    {
        return ProductFeatureValue
            ::whereIn('legacy_data->id', collect($features)->pluck('id_feature_value'))
            ->pluck('id');
    }
}
