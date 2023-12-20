<?php

namespace XtendLunar\Addons\StoreImporter\Base\Processors\Catalogue;

use FontLib\Table\Type\glyf;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Lunar\FieldTypes\Text;
use Lunar\Hub\Exceptions\InvalidProductValuesException;
use Lunar\Models\Currency;
use Lunar\Models\Price;
use Lunar\Models\ProductOption;
use Lunar\Models\ProductOptionValue;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;
use XtendLunar\Addons\StoreImporter\Base\Processors\Catalogue\Concerns\InteractsWithProductModel;
use XtendLunar\Addons\StoreImporter\Base\Processors\Processor;
use XtendLunar\Addons\StoreImporter\Models\StoreImporterResourceModel;

class ProductVariants extends Processor
{
    use InteractsWithProductModel;

    protected TaxClass $taxClass;

    protected Currency $currency;

    protected Collection $optionValues;

    protected Collection $product;

    public function __construct()
    {
        $this->taxClass = TaxClass::getDefault();
        $this->currency = Currency::getDefault();
    }

    /**
     * @throws \Exception
     */
    public function process(Collection $product, StoreImporterResourceModel $resourceModel): void
    {
        $this->product = $product;
        $this->setProductModel($product->get('productModel'));

        if (! ProductOption::count() && ! ProductOptionValue::count()) {
            throw new \Exception('Product options and values must be imported before variants.');
        }

        $this->updateOrCreateBaseVariant();

        $productModel = $this->getProductModel();
        $productModel = $productModel->refresh();
        $productModel->variant_default_id = $productModel->variants()->first()->id;
        $productModel->update();

        ProductVariant::query()->find($productModel->variant_default_id)->update([
            'base' => true,
        ]);

        $this->optionValues = $product->get('optionValues');
        $this->generateVariants();
    }

    protected function updateOrCreateBaseVariant(): void
    {
        $prices = $this->product->get('prices');

        /** @var ProductVariant $variantModel */
        $variantModel = ProductVariant::query()->updateOrCreate([
            'sku' => $this->product->get('sku'),
            'base' => true,
        ], [
            'weight_value' => $this->product->get('weight') ?? 0,
            'weight_unit' => 'lbs',
            'stock' => $this->product->get('stock') ?? 0,
            'product_id' => $this->getProductModel()->id,
            'tax_class_id' => $this->taxClass->id,
        ]);

        Price::updateOrCreate([
            'priceable_type' => ProductVariant::class,
            'priceable_id' => $variantModel->id,
        ], [
            'customer_group_id' => null,
            'currency_id' => $this->currency->id,
            'price' => $prices['default'],
            'tier' => 1,
        ]);
    }

    /**
     * @throws \Lunar\Hub\Exceptions\InvalidProductValuesException
     */
    protected function generateVariants(): void
    {
        $valueModels = ProductOptionValue::findMany($this->optionValues);

        if ($valueModels->count() != $this->optionValues->count()) {
            throw new InvalidProductValuesException(
                'One or more option values do not exist in the database'
            );
        }

        $permutations = $this->getPermutations();
        $baseVariant = $this->getProductModel()->variants->first();
        if (! $baseVariant) {
            return;
        }

        foreach ($permutations as $key => $optionsToCreate) {
            $this->createOrUpdateVariant($baseVariant, $optionsToCreate, $key);
        }
    }

    protected function createOrUpdateVariant(ProductVariant $baseVariant, array $optionsToCreate, int $key): void
    {
        $rules = config('lunar-hub.products', []);
        $uoms = ['length', 'width', 'height', 'weight', 'volume'];

        $attributesToCopy = [
            'sku',
            'gtin',
            'mpn',
            'ean',
            'shippable',
        ];

        foreach ($uoms as $uom) {
            $attributesToCopy[] = "{$uom}_value";
            $attributesToCopy[] = "{$uom}_unit";
        }

        $attributes = $baseVariant->only($attributesToCopy);

        foreach ($attributes as $attribute => $value) {
            if ($rules[$attribute]['unique'] ?? false) {
                $attributes[$attribute] = $attributes[$attribute].'-'.($key + 1);
            }
        }

        $pricing = $baseVariant->prices->map(function ($price) {
            return $price->only([
                'customer_group_id',
                'currency_id',
                'price',
                'compare_price',
                'tier',
            ]);
        });

        /** @var ProductVariant $variant */
        Schema::disableForeignKeyConstraints();

        $stock = $this->getStock($optionsToCreate, $baseVariant);

        $attributeData = array_merge($baseVariant->attribute_data ?? [], [
            'availability' => new Text($stock > 0 ? 'in-stock' : 'pre-order'),
        ]);

        $variant = ProductVariant::query()->updateOrCreate([
            'sku' => $attributes['sku'],
            'base' => false,
        ], [
            'stock' => $stock,
            'product_id' => $baseVariant->product_id,
            'tax_class_id' => $baseVariant->tax_class_id,
            'attribute_data' => $attributeData,
            ...Arr::except($attributes, ['sku']),
        ]);

        $variant->prices()->delete();
        $variant->prices()->createMany($pricing->toArray());
        Schema::enableForeignKeyConstraints();

        $variant->values()->sync($optionsToCreate);
    }

    protected function getStock(array $optionsToCreate, ProductVariant $baseVariant): int
    {
        // Stock has to be stored against one of the options, in this case it's size to use for stock lookup
        $sizeOption = ProductOption::query()->firstWhere('handle', 'size');

        // Get all option values and stock to be transformed into a lookup array
        $matchedStock = collect($this->product->get('options'))->map(
            fn($option) => collect($option)
                ->filter(fn ($value, $valueId) => $valueId !== 'images')
                ->flatMap(fn ($values) => collect($values)->keys())
                ->mapWithKeys(function ($valueId) use ($option) {
                    $stock = $option['size'][$valueId]['stock'] ?? 'color';
                    return [$valueId => $stock];
                })
                ->toArray(),
        )
            ->filter()
            ->toArray();

        foreach ($matchedStock as $stockLookup) {
            $matchingOptions = array_intersect($optionsToCreate, array_keys($stockLookup));
            if (count($matchingOptions) === count($optionsToCreate)) {
                $sizeKey = $optionsToCreate[$sizeOption->id] ?? null;
                return $stockLookup[$sizeKey] ?? 0;
            }
        }

        return $baseVariant->stock;
    }

    /**
     * Gets permutations array from the option values.
     *
     * @return array
     */
    protected function getPermutations()
    {
        return Arr::permutate(
            ProductOptionValue::findMany($this->optionValues)
                ->groupBy('product_option_id')
                ->mapWithKeys(function ($values) {
                    $optionId = $values->first()->product_option_id;

                    return [$optionId => $values->map(function ($value) {
                        return $value->id;
                    })];
                })
                ->toArray()
        );
    }
}
