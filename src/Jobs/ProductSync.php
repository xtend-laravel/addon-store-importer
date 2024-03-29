<?php

namespace XtendLunar\Addons\StoreImporter\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Lunar\FieldTypes\Text;
use Lunar\FieldTypes\TranslatedText;
use XtendLunar\Addons\StoreImporter\Base\Processors;
use XtendLunar\Addons\StoreImporter\Concerns\InteractsWithDebug;
use XtendLunar\Addons\StoreImporter\Concerns\InteractsWithPipeline;
use XtendLunar\Addons\StoreImporter\Concerns\InteractsWithResourceModel;
use XtendLunar\Addons\StoreImporter\Enums\ResourceModelStatus;
use XtendLunar\Addons\StoreImporter\Models\StoreImporterResource;
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

    public $tries = 10;

    public $timeout = 3600;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(
        protected StoreImporterResource $resource,
        protected array $productRow,
    ) {
        $this->setResourceModelByKey(
            resource: $resource,
            queryKey: 'sku',
            queryValue: $this->productRow['product_sku'] ?? null,
        );

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
                ? $this->sync()
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
            'prepare.images' => fn() => $this->prepareProductImages(),
        ])->log();
    }

    protected function prepareProduct(): void
    {
        $this->product->put('attribute_data', collect([
            'name' => new TranslatedText(
                collect($this->productRow['product_name'])->map(
                    fn (string $value) => new Text($value),
                ),
            ),
            'description' => new TranslatedText(
                collect($this->productRow['product_description'])->map(
                    fn (string $value) => new Text($value ?? '---'),
                ),
            ),
        ]));

        $this->product->put('sku', $this->productRow['product_sku'] ?? null);
        $this->product->put('weight', $this->productRow['product_weight'] ?? null);
        $this->product->put('status', $this->productRow['product_status'] ?? 'published');
        $this->product->put('prices', [
            'default' => preg_replace('/[^0-9]/', '', $this->productRow['product_price_default']),
        ]);
    }

    protected function prepareCollections(): void
    {
        $collections = collect($this->productRow)
            ->filter(fn($value, $key) => Str::startsWith($key, 'product_collections_'))
            ->flatMap(
                function ($value, $key) {
                    $key = Str::of($key)->after('product_collections_')->value();
                    return [
                        $key => $value,
                    ];
                },
            );

        $this->product->put('collections', $collections);
    }

    protected function prepareOptions(): void
    {
        $this->mergeColorOptions();
        $options = collect($this->productRow['variants'])
            ->flatMap(
                fn (array $variant) => collect([
                    collect($variant)
                        ->filter(fn ($value, $key) => Str::startsWith($key, 'product_option_'))
                        ->flatMap(
                            function ($values, $key) {
                                $handle = Str::of($key)->after('product_option_')->value();
                                return [
                                    $handle => collect($values)->map(
                                        fn(mixed $value) => $this->prepareOptionValue($handle, $value),
                                    )->filter()->toArray(),
                                ];
                            },
                        )
                        ->merge([
                            'images' => collect($variant['product_images'])
                                ->map(
                                    function (string $image) {
                                        $originalImage = Str::of($image)->before('(')->trim()->value();
                                        $tempImage = Str::of($image)->betweenFirst('(', ')')->trim()->value();
                                        return $tempImage.'?'.$originalImage;

                                        // $imageArr = explode('.png', $image);
                                        // $originalImage = $imageArr[0].'.png';
                                        // $tempImage = Str::of($imageArr[1])->betweenFirst('(', ')')->trim()->value();
                                        // return $tempImage.'?'.$originalImage;
                                    },
                                )
                                ->toArray(),
                        ])
                        ->filter()
                        ->toArray(),
                ])->toArray(),
            );

        $this->product->put('options', $options);
    }

    protected function mergeColorOptions(): void
    {
        // @todo this needs to be moved to a project transformer this logic should not be in the core product sync
        $this->productRow['variants'] = collect($this->productRow['variants'])->map(
            function (array $variant) {
                $variant['product_option_color'] = [
                    [
                        'name' => new TranslatedText(
                            collect($variant['product_option_primary_color'])->map(
                                fn (string $value) => new Text($value),
                            ),
                        ),
                        'colors' => $variant['product_option_color'],
                    ],
                ];
                unset($variant['product_option_primary_color']);
                return $variant;
            },
        );
    }

    protected function prepareOptionValue(string $handle, mixed $value): array
    {
        if ($handle === 'color') {
            return [
                'name' => $value['name'],
                'color' => $value['colors'][0] ?? null,
                'primary_color' => $value['colors'][0] ?? null,
                'secondary_color' => $value['colors'][1] ?? null,
                'tertiary_color' => $value['colors'][2] ?? null,
            ];
        }

        if (!$value) {
            $value = 'S:0,M:0,L:0,XL:0';
        }

        if ($handle === 'size') {
            [$value, $size] = explode(':', $value);
        }

        return [
            'name' => is_string($value)
                ? new TranslatedText([
                    'en' => new Text($value),
                    'fr' => new Text($value),
                    'ar' => new Text($value),
                ])
                : $value['name'],
            'stock' => (int)$size ?? 9999,
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
                                'fr' => new Text(Str::headline($feature)),
                                'ar' => new Text(Str::headline($feature)),
                            ]),
                            'handle' => $feature,
                            'values' => new TranslatedText(
                                collect($values)->map(
                                    fn (string $value) => new Text($value),
                                ),
                            ),
                        ],
                    ];
                },
            );

        $this->product->put('features', $features);
    }

    protected function prepareProductImages(): void
    {
        $images = collect($this->productRow['product_images'])
            ->flatMap(fn (array $images, $key) => [
                $key => collect($images)
                    ->map(
                        function (string $image) {
                            $originalImage = Str::of($image)->before('(')->trim()->value();
                            $tempImage = Str::of($image)->betweenFirst('(', ')')->trim()->value();
                            return $tempImage.'?'.$originalImage;

                            // $imageArr = explode('.png', $image);
                            // $originalImage = $imageArr[0].'.png';
                            // $tempImage = Str::of($imageArr[1])->betweenFirst('(', ')')->trim()->value();
                            // return $tempImage.'?'.$originalImage;
                        },
                    )
            ])
            ->toArray();

        $this->product->put('images', $images);
    }

    protected function sync(): StoreImporterResourceModel
    {
        $this->prepareThroughPipeline(
            passable: [
                'product' => $this->product,
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

        return $this->resourceModel;
    }
}
