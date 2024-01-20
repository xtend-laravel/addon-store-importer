<?php

namespace XtendLunar\Addons\StoreImporter\Commands;

use Lunar\Models\Product;
use XtendLunar\Addons\StoreImporter\Base\Airtable;
use XtendLunar\Features\ProductFeatures\Models\ProductFeature;

class SyncProductSeasons extends AirtableBaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'at:sync-product-seasons';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This command will sync product seasons using the Airtable API.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $seasonsSkuMap = collect(Airtable::products())
            ->filter(fn($item) => $item['fields']['Import Only'] ?? false)
            ->mapWithKeys(
                fn($item) => [$item['fields']['Base SKU'] => $item['fields']['Season'][0] ?? null],
            );

        $seasonFeature = $this->ensureSeasonsExist();

        $seasonsSkuMap->each(
            function ($season, $sku) use ($seasonFeature) {
                $product = Product::query()->firstWhere('sku', $sku);
                $featureValue = $seasonFeature
                    ->values()
                    ->firstWhere('name->en', $season);

                if ($featureValue && $product) {
                    $product->featureValues()->detach($seasonFeature->values()->pluck('id'));
                    $product->featureValues()->attach($featureValue->id);

                    $this->info("Attached season {$season} to product {$product->translateAttribute('name')}.");
                }
            },
        );

        return self::SUCCESS;
    }

    protected function ensureSeasonsExist(): ProductFeature
    {
        /** @var ProductFeature $feature */
        $feature = ProductFeature::query()->updateOrCreate([
            'handle' => 'season',
        ], [
            'name' => collect([
                'en' => 'Season',
                'fr' => 'Saison',
                'ar' => 'موسم',
            ]),
        ]);

        $feature->values()->updateOrCreate([
            'name->en' => 'Summer',
        ], [
            'name' => collect([
                'en' => 'Summer',
                'fr' => 'Été',
                'ar' => 'الصيف',
            ]),
        ]);

        $feature->values()->updateOrCreate([
            'name->en' => 'Winter',
        ], [
            'name' => collect([
                'en' => 'Winter',
                'fr' => 'Hiver',
                'ar' => 'شتاء',
            ]),
        ]);

        return $feature;
    }
}
