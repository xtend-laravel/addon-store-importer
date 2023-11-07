<?php

namespace XtendLunar\Addons\StoreImporter\Base\Processors\Catalogue;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Lunar\FieldTypes\Text;
use Lunar\FieldTypes\TranslatedText;
use Lunar\Models\ProductOptionValue;
use Xtend\Extensions\Lunar\Core\Models\ProductOption;
use XtendLunar\Addons\StoreImporter\Base\Processors\Catalogue\Concerns\InteractsWithProductModel;
use XtendLunar\Addons\StoreImporter\Base\Processors\Processor;
use XtendLunar\Addons\StoreImporter\Models\StoreImporterResourceModel;

class ProductOptions extends Processor
{
    use InteractsWithProductModel;

    protected Collection $optionValues;

    protected Collection $product;

    public function process(Collection $product, StoreImporterResourceModel $resourceModel): void
    {
        $this->product = $product;

        $this->optionValues = collect();
        $this->setProductModel($product->get('productModel'));

        $options = $this->prepareOptions();

        if ($options->isNotEmpty()) {
            $options->each(function (Collection $values, string $handle) use ($product) {
                $option = $this->createOptionByHandle($handle);
                $this->createOptionValues($option, $values);
            });

            $product->put('optionValues', $this->optionValues->pluck('id')->unique());
            $this->product->put('options', $this->transformOptions());
        }
    }

    protected function createOptionByHandle(string $handle): ProductOption
    {
        $query = ProductOption::query()->where('handle', $handle);
        $name = new TranslatedText([
            'en' => new Text(Str::headline($handle)),
        ]);

        /** @var Builder | ProductOption $query */
        return $query->exists()
            ? $query->first()
            : ProductOption::create([
                'name' => $name,
                'label' => $name,
                'handle' => $handle,
            ]);
    }

    protected function createOptionValues(ProductOption $option, Collection $values): void
    {
        $values->each(function (array $value) use ($option) {
            /** @var TranslatedText $name */
            $name = $value['name'];

            if ($name instanceof TranslatedText) {
                $name = $name->getValue()->get('en')->getValue();
            }

            $optionValue = $option->values()->updateOrCreate([
                'name->en' => $name,
            ], Arr::except($value, ['stock']));

            $this->optionValues->push($optionValue);
        });
    }

    protected function transformOptions(): Collection
    {
        return collect($this->product->get('options'))->map(function (array $option) {
            $images = collect($option['images'] ?? []);
            return collect($option)
                ->keys()
                ->filter(fn ($key) => $option[$key] ?? false)
                ->mapWithKeys(function ($key) use ($option) {
                    return [
                        $key => collect($option[$key])
                            ->filter(fn ($value) => $value['name'] ?? null instanceof TranslatedText)
                            ->mapWithKeys(function ($value) {
                                $name = $value['name']->getValue()->get('en')->getValue();
                                $key = ProductOptionValue::query()->firstWhere('name->en', $name)->id;
                                return [$key => $value];
                            }),
                    ];
                })
                ->filter(fn (Collection $value) => $value->isNotEmpty())
                ->merge(['images' => $images])
                ->toArray();
        });
    }

    protected function prepareOptions(): Collection
    {
        return collect($this->product->get('options'))
            ->reduce(function ($carry, $option) {
                unset($option['images']);
                foreach ($option as $key => $values) {
                    $carry[$key] ??= collect();
                    $carry[$key] = $carry[$key]->merge($values);
                }
                return $carry;
            }, collect());
    }
}
