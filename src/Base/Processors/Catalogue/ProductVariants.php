<?php

namespace XtendLunar\Addons\StoreImporter\Base\Processors\Catalogue;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Lunar\Models\Currency;
use Lunar\Models\Price;
use Lunar\Models\Product;
use Lunar\Models\ProductOption;
use Lunar\Models\ProductOptionValue;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;
use XtendLunar\Addons\StoreImporter\Base\Processors\Processor;
use XtendLunar\Addons\StoreImporter\Models\StoreImporterResourceModel;

class ProductVariants extends Processor
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
        $price = number_format(Arr::get($productModel, 'legacy.price'), 2, '', '');

        if (! $productModel->variants->count()) {
            $this->createDefaultProductVariant($productModel, $product, $price);
            $productModel = $productModel->refresh();
        }

        /** @var Collection $combinations */
        $combinations = $product->get('combinations');
        if (! $product->has('combinations') || (! ProductOption::count() && ! ProductOptionValue::count())) {
            return $product;
        }

        if ($productModel->variants->count() >= 1) {
            $combinations
                ->filter(fn ($combination) => filled($combination->get('sku')))
                ->each(fn ($combination) => $this->saveVariant($combination, $productModel, $price));
        }

        $productModel = $productModel->refresh();
        $productModel->variant_default_id = $productModel->variants->first()->id;
        $productModel->update();

        return [$product];
    }

    protected function createDefaultProductVariant(Product $productModel, Collection $product, int $price): void
    {
        $variant = ProductVariant::create([
            'base' => true,
            'product_id' => $productModel->id,
            'purchasable' => 'always',
            'shippable' => true,
            'backorder' => 0,
            'sku' => Arr::get($product, 'legacy.sku'),
            'tax_class_id' => $this->taxClass->id,
            'stock' => $product->get('stock'),
            'legacy_data' => $product->get('legacy'),
        ]);

        Price::create([
            'customer_group_id' => null,
            'currency_id' => $this->currency->id,
            'priceable_type' => ProductVariant::class,
            'priceable_id' => $variant->id,
            'price' => $price,
            'tier' => 1,
        ]);
    }

    protected function saveVariant(Collection $combination, Product $productModel, int $price): void
    {
        $combinationPrice = (int) number_format(Arr::get($combination, 'legacy.price'), 2, '', '');

        $variant = ProductVariant::updateOrCreate([
            'sku' => $combination->get('sku'),
        ], [
            'product_id' => $productModel->id,
            'purchasable' => 'always',
            'shippable' => true,
            'backorder' => 0,
            'tax_class_id' => $this->taxClass->id,
            'stock' => $combination->get('stock'),
            'legacy_data' => $combination->get('legacy'),
        ]);

        Price::updateOrCreate([
            'priceable_type' => ProductVariant::class,
            'priceable_id' => $variant->id,
        ], [
            'customer_group_id' => null,
            'currency_id' => $this->currency->id,
            'price' => $price + $combinationPrice,
            'tier' => 1,
        ]);

        $optionValueIds = $this->getOptionValueIds($combination);
        $variant->values()->sync($optionValueIds);
    }

    protected function getOptionValueIds(Collection $combination): array
    {
        $optionValues = ProductOptionValue::get();
        $values = Arr::get($combination, 'associations.product_option_values');

        $optionValueIds = [];
        $defaultLocale = app()->getLocale();
        foreach ($values as $value) {
            $valueModel = $optionValues->first(fn ($val) => $value['name'][$defaultLocale] == $val->translate('name'));
            if (! $valueModel) {
                continue;
            }

            $optionValueIds[] = $valueModel->id;
        }

        return $optionValueIds;
    }
}
