<?php

namespace XtendLunar\Addons\StoreImporter\Base\Processors\Catalogue;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
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
        $this->setProductModel($product->get('productModel'));

        if (! ProductOption::count() && ! ProductOptionValue::count()) {
            throw new \Exception('Product options and values must be imported before variants.');
        }

        $this->deleteVariants();
        $this->createBaseVariant($product);

        $optionValues = $product->get('optionValues');

        $this->generateVariants($optionValues);

        $productModel = $this->getProductModel();
        $productModel = $productModel->refresh();
        $productModel->variant_default_id = $productModel->variants()->first()->id;
        $productModel->update();

        ProductVariant::query()->find($productModel->variant_default_id)->update([
            'base' => true,
        ]);
    }

    protected function deleteVariants(): void
    {
        // @todo Delete all variants and prices needs to be configurable
        Schema::disableForeignKeyConstraints();
        $this->getProductModel()->variants()->delete();
        $this->getProductModel()->prices()->delete();
        Schema::enableForeignKeyConstraints();
    }

    protected function createBaseVariant(Collection $product): void
    {
        $prices = $product->get('prices');

        /** @var ProductVariant $variantModel */
        $variantModel = ProductVariant::query()->create([
            'sku' => $product->get('sku'),
            'base' => true,
            'weight_value' => $product->get('weight') ?? 0,
            'weight_unit' => 'lbs',
            'stock' => $product->get('stock') ?? 99999,
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

    protected function generateVariants(Collection $optionValues): void
    {
        $valueModels = ProductOptionValue::findMany($optionValues);

        if ($valueModels->count() != count($optionValues)) {
            throw new InvalidProductValuesException(
                'One or more option values do not exist in the database'
            );
        }

        $permutations = $this->getPermutations($optionValues);
        $baseVariant = $this->getProductModel()->variants->first();

        foreach ($permutations as $key => $optionsToCreate) {
            $this->createVariant($baseVariant, $optionsToCreate, $key);
        }

        if ($baseVariant) {
            $baseVariant->values()->detach();
            $baseVariant->prices()->delete();
            $baseVariant->delete();
        }
    }

    protected function createVariant(ProductVariant $baseVariant, array $optionsToCreate, int $key): void
    {
        $variant = new ProductVariant();
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

        $variant->stock = $baseVariant->stock ?? 0;
        $variant->product_id = $baseVariant->product_id;
        $variant->tax_class_id = $baseVariant->tax_class_id;
        $variant->attribute_data = $baseVariant->attribute_data;
        $variant->fill($attributes);
        $variant->save();
        $variant->values()->attach($optionsToCreate);
        $variant->prices()->createMany($pricing->toArray());
    }

    /**
     * Gets permutations array from the option values.
     *
     * @return array
     */
    protected function getPermutations(Collection $optionValues)
    {
        return Arr::permutate(
            ProductOptionValue::findMany($optionValues)
                ->groupBy('product_option_id')
                ->mapWithKeys(function ($values) {
                    $optionId = $values->first()->product_option_id;

                    return [$optionId => $values->map(function ($value) {
                        return $value->id;
                    })];
                })->toArray()
        );
    }
}
