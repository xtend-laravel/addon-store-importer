<?php

namespace XtendLunar\Addons\StoreImporter\Livewire\Pages;

use Illuminate\View\View;
use Livewire\Component;
use Lunar\Hub\Http\Livewire\Traits\Notifies;
use XtendLunar\Addons\StoreImporter\Airtable\Concerns\InteractsWithProducts;
use XtendLunar\Addons\StoreImporter\Base\Transformers\Concerns\InteractWithProcessor;
use XtendLunar\Addons\StoreImporter\Base\Transformers\Concerns\InteractWithTransformer;
use XtendLunar\Features\FormBuilder\Livewire\Concerns\HasForms;

class StoreImporter extends Component
{
    use HasForms, Notifies;
    use InteractsWithProducts;
    use InteractWithTransformer;
    use InteractWithProcessor;

    public function syncProducts()
    {
        $productsMap = $this->products()
            ->importOnly()
            ->primary()
            ->mapToSku()
            ->get()
            ->map(function ($product) {
                $product = $this->transformProduct($product);
                $product['variants'] = $this->productVariants($product)
                    ->transform(
                        fn($productVariant) => collect($this->transformVariant($productVariant))
                            ->only(['color', 'size', 'images', 'variant_primary'])
                            ->toArray(),
                    );
                $product['product_images'] = $product['variants']->pluck('images')->toArray();
                return $product;
            });

        $productsMap->each(
            function ($product, $sku) {
                $this->existsInStore($sku)
                    ? $this->updateProduct($sku, $product)
                    : $this->createProduct($sku, $product);
            },
        );
    }

    protected function updateProduct(string $sku, array $product): void
    {
        $this->syncProduct(
            product: $product,
            withImages: false
        );
        // dump("Product with SKU {$sku} has been updated.");
    }

    protected function createProduct(string $sku, array $product)
    {
        $this->syncProduct(
            product: $product,
            withImages: false
        );
        // dump("Product with SKU {$sku} does not exist in the store.");
    }

    public function render(): View
    {
        return view('adminhub::livewire.pages.store-importer')
            ->layout('adminhub::layouts.app', [
                'title' => __('Store Importer'),
            ]);
    }
}
