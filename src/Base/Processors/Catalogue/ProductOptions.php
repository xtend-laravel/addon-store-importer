<?php

namespace XtendLunar\Addons\StoreImporter\Base\Processors\Catalogue;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Lunar\FieldTypes\Text;
use Lunar\FieldTypes\TranslatedText;
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

        $options = $this->prepareOptions($product);
        if ($options->isNotEmpty()) {
            $options->each(function (Collection $values, string $handle) use ($product) {
                $option = $this->createOptionByHandle($handle);
                $this->createOptionValues($option, $values, $handle);
            });

            $product->put('optionValues', $this->optionValues->pluck('id'));
        }
    }

    protected function createOptionByHandle(string $handle): ProductOption
    {
        $query = ProductOption::query()->where('handle', $handle);
        $name = new TranslatedText([
            'en' => new Text(Str::headline($handle)),
        ]);

        if (!$query->exists()) {
            Log::driver('slack')->warning('Create new option', [
                'name' => $name,
                'label' => $name,
                'handle' => $handle,
            ]);
        }

        /** @var Builder | ProductOption $query */
        return $query->exists()
            ? $query->first()
            : ProductOption::create([
                'name' => $name,
                'label' => $name,
                'handle' => $handle,
            ]);
    }

    protected function createOptionValues(ProductOption $option, Collection $values, string $handle): void
    {
        if ($handle === 'color') {
            $values = collect([$values->toArray()]);
        }

        $values->each(function (array $value) use ($option) {
            $optionValue = $option->values()->updateOrCreate([
                'name->en' => $value['name'],
            ], Arr::except($value, ['name']));

            $this->optionValues->push($optionValue);
        });
    }

    protected function prepareOptions(Collection $product): Collection
    {
        return collect($product->get('options'))
            ->reduce(function ($carry, $option) {
                unset($option['images']);
                foreach ($option as $key => $values) {
                    $carry[$key] ??= collect();
                    $carry[$key] = $carry[$key]->merge($values)->unique();
                }
                return $carry;
            }, collect());
    }
}
