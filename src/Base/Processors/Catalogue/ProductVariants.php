<?php

namespace XtendLunar\Addons\StoreImporter\Base\Processors\Catalogue;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Lunar\Hub\Jobs\Products\GenerateVariants;
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

        if (! $product->has('variants') || (! ProductOption::count() && ! ProductOptionValue::count())) {
            throw new \Exception('Product options and values must be imported before variants.');
        }

        /** @var Collection $variants */
        $variants = $product->get('variants');

        $this->deleteVariants();

        $variants
            ->filter(fn ($variant) => filled($variant->get('sku')))
            ->each(fn ($variant) => $this->saveVariant($variant, $product));

        $productModel = $this->getProductModel();
        $productModel = $productModel->refresh();
        $productModel->variant_default_id = $productModel->variants()->first()->id;
        $productModel->update();
    }

    protected function deleteVariants(): void
    {
        // @todo Delete all variants and prices needs to be configurable
        Schema::disableForeignKeyConstraints();
        $this->getProductModel()->variants()->delete();
        $this->getProductModel()->prices()->delete();
        Schema::enableForeignKeyConstraints();
    }

    protected function saveVariant(Collection $variant, Collection $product): void
    {
        $prices = $product->get('prices');

        /** @var ProductVariant $variantModel */
        $variantModel = ProductVariant::updateOrCreate([
            'sku' => $variant->get('sku'),
        ], [
            'product_id' => $this->getProductModel()->id,
            'purchasable' => $variant->get('purchasable'),
            'shippable' => $variant->get('shippable'),
            'backorder' => $variant->get('backorder'),
            'tax_class_id' => $this->taxClass->id,
            'stock' => $variant->get('stock'),
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

        $optionValues = $product->get('optionValues');

        // @todo Overwrite this job with our own custom implementation to include base and featured variants
        GenerateVariants::dispatchSync($this->getProductModel(), $optionValues);
    }
}
