<?php

namespace XtendLunar\Addons\StoreImporter\Base\Processors\Catalogue;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Xtend\Extensions\Lunar\Core\Models\ProductOption;
use XtendLunar\Addons\StoreImporter\Base\Processors\Catalogue\Concerns\InteractsWithProductModel;
use XtendLunar\Addons\StoreImporter\Base\Processors\Processor;
use XtendLunar\Addons\StoreImporter\Models\StoreImporterResourceModel;
use XtendLunar\Features\ProductFeatures\Models\ProductFeature;

class ProductFeatures extends Processor
{
    use InteractsWithProductModel;

    public function process(Collection $product, StoreImporterResourceModel $resourceModel): void
    {
        $this->setProductModel($product->get('productModel'));

        $features = collect($product->get('features'))->map(
            fn (array $feature, $handle) => $this->getFeature($feature, $handle)
        );

        if ($features->isNotEmpty()) {
            $features->each(function (ProductFeature $feature) use ($product) {
                $handle = $feature->handle;
                $values = collect($product->get('features')[$handle]['values'] ?? []);
                $values->each(function (array $value) use ($feature) {
                    $feature->values()->updateOrCreate([
                        'name->en' => $value['name']->getValue()->get('en')->getValue(),
                    ], Arr::except($value, ['name']));
                });
            });
        }
    }

    protected function getFeature(array $feature, string $handle): ProductFeature
    {
        $query = ProductFeature::query()->where('handle', $handle);

        /** @var Builder | ProductFeature $query */
        return $query->exists()
            ? $query->first()
            : ProductFeature::create([
                'name' => $feature['name'],
                'handle' => $handle,
            ]);
    }
}
