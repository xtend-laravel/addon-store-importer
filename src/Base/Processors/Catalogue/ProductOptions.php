<?php

namespace XtendLunar\Addons\StoreImporter\Base\Processors\Catalogue;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Xtend\Extensions\Lunar\Core\Models\ProductOption;
use XtendLunar\Addons\StoreImporter\Base\Processors\Catalogue\Concerns\InteractsWithProductModel;
use XtendLunar\Addons\StoreImporter\Base\Processors\Processor;
use XtendLunar\Addons\StoreImporter\Models\StoreImporterResourceModel;

class ProductOptions extends Processor
{
    use InteractsWithProductModel;

    protected Collection $optionValues;

    public function process(Collection $product, StoreImporterResourceModel $resourceModel): void
    {
        $this->optionValues = collect();
        $this->setProductModel($product->get('productModel'));

        $options = collect($product->get('options'))->map(
            fn (array $option, $handle) => $this->getOption($option, $handle)
        );

        if ($options->isNotEmpty()) {
            $options->each(function (ProductOption $option) use ($product) {
                $handle = $option->handle;
                $values = collect($product->get('options')[$handle]['values'] ?? []);
                $values->each(function (array $value) use ($option) {
                    $optionValue = $option->values()->updateOrCreate([
                        'name->en' => $value['name']->getValue()->get('en')->getValue(),
                    ], Arr::except($value, ['name']));
                    $this->optionValues->push($optionValue);
                });
            });

            $product->put('optionValues', $this->optionValues->pluck('id'));
        }
    }

    protected function getOption(array $option, string $handle): ProductOption
    {
        $query = ProductOption::query()->where('handle', $handle);

        /** @var Builder | ProductOption $query */
        return $query->exists()
            ? $query->first()
            : ProductOption::create([
                'name' => $option['name'],
                'label' => $option['label'],
                'handle' => $handle,
            ]);
    }
}
