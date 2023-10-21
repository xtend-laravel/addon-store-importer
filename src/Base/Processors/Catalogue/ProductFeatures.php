<?php

namespace XtendLunar\Addons\StoreImporter\Base\Processors\Catalogue;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Lunar\FieldTypes\TranslatedText;
use Xtend\Extensions\Lunar\Core\Models\ProductOption;
use XtendLunar\Addons\StoreImporter\Base\Processors\Catalogue\Concerns\InteractsWithProductModel;
use XtendLunar\Addons\StoreImporter\Base\Processors\Processor;
use XtendLunar\Addons\StoreImporter\Models\StoreImporterResourceModel;
use XtendLunar\Features\ProductFeatures\Models\ProductFeature;

class ProductFeatures extends Processor
{
    use InteractsWithProductModel;

    protected array $featureValueIds = [];

    public function process(Collection $product, StoreImporterResourceModel $resourceModel): void
    {
        $this->setProductModel($product->get('productModel'));

        $features = collect($product->get('features'))->map(
            fn (array $feature, $handle) => $this->getFeature($feature, $handle)
        );

        if ($features->isNotEmpty()) {
            $this->getProductModel()->featureValues()->detach();

            $features->each(function (ProductFeature $feature) use ($product) {
                $handle = $feature->handle;
                $values = $product->get('features')[$handle]['values'] ?? [];

                if ($values instanceof TranslatedText) {
                    $name = $values->getValue()->get('en')->getValue();
                } else {
                    $name = $values['name'] ?? null;
                }

                $featureValue = $feature->values()->updateOrCreate([
                    'name->en' => $name,
                ], ['name' => $values]);

                $this->featureValueIds[] = $featureValue->id;

                // $values->each(function (array $value) use ($feature) {
                //     dd($value);
                //     $featureValue = $feature->values()->updateOrCreate([
                //         'name->en' => $value['name']->getValue()->get('en')->getValue(),
                //     ], Arr::except($value, ['name']));
                //
                //     $this->featureValueIds[] = $featureValue->id;
                // });
            });

            $this->getProductModel()->featureValues()->sync($this->featureValueIds);
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
