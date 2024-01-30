<?php

namespace XtendLunar\Addons\StoreImporter\Commands;

use XtendLunar\Addons\StoreImporter\Airtable\Concerns\InteractsWithProducts;
use XtendLunar\Addons\StoreImporter\Base\Transformers\Concerns\InteractWithProcessor;
use XtendLunar\Addons\StoreImporter\Base\Transformers\Concerns\InteractWithTransformer;

class InventoryUpdateSync extends AirtableBaseCommand
{
    use InteractsWithProducts;
    use InteractWithTransformer;
    use InteractWithProcessor;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'at:inventory-update-sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This command will sync inventory updated data from Airtable with the products in the store.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
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

        return self::SUCCESS;
    }

    protected function updateProduct(string $sku, array $product): void
    {
        $this->syncProduct($product);
        $this->info("Product with SKU {$sku} has been updated.");
    }

    protected function createProduct(string $sku, array $product)
    {
        $this->syncProduct($product);
        $this->warn("Product with SKU {$sku} does not exist in the store.");
    }
}
