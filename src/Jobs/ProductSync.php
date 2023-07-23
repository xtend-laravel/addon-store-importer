<?php

namespace XtendLunar\Addons\StoreImporter\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lunar\FieldTypes\Text;
use Lunar\FieldTypes\TranslatedText;
use XtendLunar\Addons\StoreImporter\Base\Processors;
use XtendLunar\Addons\StoreImporter\Concerns\InteractsWithDebug;
use XtendLunar\Addons\StoreImporter\Concerns\InteractsWithPipeline;
use XtendLunar\Addons\StoreImporter\Concerns\InteractsWithResourceModel;
use XtendLunar\Addons\StoreImporter\Enums\ResourceModelStatus;
use XtendLunar\Addons\StoreImporter\Models\StoreImporterResourceModel;

class ProductSync implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithDebug;
    use InteractsWithPipeline;
    use InteractsWithResourceModel;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected Collection $product;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(
        protected Model|StoreImporterResourceModel $resourceModel,
        protected array $productRow,
    ) {
        // @todo check if product exists by variant sku

        $this->setResourceModel($resourceModel);

        $this->product = collect();
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->benchmark([
            'prepare' => fn() => $this->prepare(),
            'sync' => fn() => $this->product->isNotEmpty()
                ? DB::transaction(fn() => $this->sync())
                : null,
        ])->log();
    }

    protected function prepare(): void
    {
        // @todo Move this to it's own classes something like LunarMappingPipeline

        $this->benchmark([
            'prepare.product' => fn() => $this->prepareProduct(),
            'prepare.collections' => fn() => $this->prepareCollections(),
            'prepare.options' => fn() => $this->prepareOptions(),
            'prepare.features' => fn() => $this->prepareFeatures(),
            'prepare.variants' => fn() => $this->prepareVariants(),
            'prepare.images' => fn() => $this->prepareProductImages(),
        ])->log();
    }

    protected function prepareProduct(): void
    {
        $this->product->put('attribute_data', collect([
            'name' => new TranslatedText([
                'en' => new Text($this->productRow['product_name'] ?? '---'),
            ]),
        ]));

        $this->product->put('sku', $this->productRow['product_sku'] ?? null);
        $this->product->put('status', $this->productRow['product_status'] ?? 'published');
    }

    protected function prepareCollections(): void
    {
        $collections = collect($this->productRow['product_collections'])
            ->filter(
                fn (string $collection) => \Lunar\Models\Collection::query()
                    ->whereJsonContains('attribute_data->name->value->en', $collection)
                    ->exists(),
            );

        $this->product->put('collections', $collections);
    }

    protected function prepareOptions(): void
    {
        $options = collect($this->productRow)
            ->filter(fn($value, $key) => Str::startsWith($key, 'product_option_'))
            ->flatMap(
                function ($values, $option) {
                    $option = Str::of($option)->after('product_option_')->value();
                    return [
                        $option => [
                            'name' => new TranslatedText([
                                'en' => new Text(Str::headline($option)),
                            ]),
                            'label' => new TranslatedText([
                                'en' => new Text(Str::headline($option)),
                            ]),
                            'values' => collect($values)->map(
                                fn (string $value) => $this->prepareOptionValue($option, $value),
                            )->filter(),
                        ],
                    ];
                },
            );

        $this->product->put('options', $options);
    }

    protected function prepareOptionValue(string $option, string $value): array
    {
        if (Str::of($option)->contains('color')) {

            // @todo Replace temp values once the CSV is updated
            $value = 'Gold:#f3c999 | Brown:#a9582e | Black:#000000';

            $color = Str::of($value)->contains('|')
                ? Str::of($value)->explode('|')->map(
                    function (string $color) {
                        $color = Str::of($color)->explode(':');
                        return [
                            'name' => trim($color->first()),
                            'hex' => $color->last(),
                        ];
                    },
                )
                : collect(
                    Str::of($value)->after(':')->value(),
                );

            $primaryColor = $color->first()['hex'] ?? null;
            $secondaryColor = $color->get(1)['hex'] ?? null;
            $tertiaryColor = $color->get(2)['hex'] ?? null;

            $name = $color->pluck('name')->implode(' ');

            return [
                'name' => new TranslatedText([
                    'en' => new Text(Str::headline($name)),
                ]),
                'color' => $primaryColor ?? null,
                'primary_color' => $primaryColor ?? null,
                'secondary_color' => $secondaryColor ?? null,
                'tertiary_color' => $tertiaryColor ?? null,
            ];
        }

        return [
            'name' => new TranslatedText([
                'en' => new Text(Str::headline($value)),
            ]),
        ];
    }

    protected function prepareFeatures(): void
    {
        $features = collect($this->productRow)
            ->filter(fn($value, $key) => Str::startsWith($key, 'product_feature_'))
            ->flatMap(
                function ($values, $feature) {
                    $feature = Str::of($feature)->after('product_feature_')->value();
                    return [
                        $feature => [
                            'name' => new TranslatedText([
                                'en' => new Text(Str::headline($feature)),
                            ]),
                            'handle' => $feature,
                            'values' => collect($values)->map(
                                fn (string $value) => $this->prepareFeatureValue($value),
                            )->filter(),
                        ],
                    ];
                },
            );

        $this->product->put('features', $features);
    }

    protected function prepareFeatureValue(string $value): array
    {
        return [
            'name' => new TranslatedText([
                'en' => new Text(Str::headline($value)),
            ]),
        ];
    }

    protected function prepareVariants(): void
    {
        $variants = collect($this->productRow['product_variants'])
            ->transform(
                fn (array $variants) => collect($variants)->flatMap(
                    function ($variant, $key) {
                        $key = Str::of($key)->after('product_variant_')->value();
                        return [$key => $variant];
                    },
                )->toArray(),
            );

        $variants->transform(
            fn (array $variant) => [
                'tax_class_id' => $variant['tax_class_id'] ?? null,
                'base' => $variant['base'] ?? false,
                'primary' => $variant['primary'] ?? false,
                'unit_quantity' => $variant['unit_quantity'] ?? 1,
                'sku' => $variant['sku'] ?? null,
                'gtin' => $variant['gtin'] ?? null,
                'mpn' => $variant['mpn'] ?? null,
                'length_value' => $variant['length_value'] ?? null,
                'length_unit' => $variant['length_unit'] ?? null,
                'width_value' => $variant['width_value'] ?? null,
                'width_unit' => $variant['width_unit'] ?? null,
                'height_value' => $variant['height_value'] ?? null,
                'height_unit' => $variant['height_unit'] ?? null,
                'weight_value' => $variant['weight_value'] ?? null,
                'weight_unit' => $variant['weight_unit'] ?? null,
                'volume_value' => $variant['volume_value'] ?? null,
                'shippable' => $variant['shippable'] ?? true,
                'stock' => $variant['stock'] ?? 99999,
                'backorder' => $variant['backorder'] ?? false,
                'purchasable' => $variant['purchasable'] ?? 'always',
            ],
        );

        $this->product->put('variants', $variants);
    }

    protected function prepareProductImages(): void
    {
        // @todo Move this to a transformer class MarkdownImagesTransformer
        $images = collect($this->productRow['product_images'])
            ->flatMap(fn (string $image) => Str::of($image)->matchAll('/\((https?:\/\/[^)]+)\)/'))
            ->toArray();

        $this->product->put('productImages', $images);
    }

    protected function sync(): void
    {
        $this->prepareThroughPipeline(
            passable: [
                'resource' => $this->product,
                'resourceModel' => $this->resourceModel,
            ],
            pipes: [
                Processors\Catalogue\Product::class,
                Processors\Catalogue\Collections::class,
                Processors\Catalogue\ProductOptions::class,
                Processors\Catalogue\ProductFeatures::class,
                Processors\Catalogue\ProductVariants::class,
                Processors\Catalogue\ProductImages::class,
            ],
        );

        $this->resourceModel->status = ResourceModelStatus::Created;
        $this->resourceModel->save();
    }
}
