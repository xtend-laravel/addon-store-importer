<?php

namespace XtendLunar\Addons\StoreImporter\Base\Transformers\Concerns;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use XtendLunar\Addons\StoreImporter\Base\Transformers;
use XtendLunar\Addons\StoreImporter\Concerns\InteractsWithPipeline;
use XtendLunar\Addons\StoreImporter\Jobs\ProductSync;

trait InteractWithTransformer
{
    use InteractsWithPipeline;

    protected function transformProduct(array $product): mixed
    {
        return $this->prepareThroughPipeline(
            passable: [
                'product' => $product,
            ],
            pipes: [
                Transformers\Product\FieldMapTransformer::class,
                Transformers\Product\AttributeDataTransformer::class,
                Transformers\Product\FeaturesTransformer::class,
                Transformers\Product\ImagesTransformer::class,
            ],
        );
    }

    protected function transformVariant(array $productVariant): mixed
    {
        $productVariant = $this->transformProduct($productVariant['fields']);

        return $this->prepareThroughPipeline(
            passable: $productVariant,
            pipes: [
                Transformers\Variant\ColorTransformer::class,
                Transformers\Variant\SizeStockTransformer::class,
                Transformers\Variant\ImagesTransformer::class,
            ],
        );
    }

    protected function syncProductRows(Collection $rows): void
    {
        $rows
            ->filter(fn (array $rowProperties) => $rowProperties['Import Only'] === 'checked')
            ->filter(fn (array $rowProperties) => $rowProperties['Primary'] === 'checked')
            ->filter(fn (array $rowProperties) => $rowProperties['Base SKU'] === 'AWR-JNM-043')
            ->each(function(array $rowProperties) use ($rows) {
                $productRow = $this->getProductRow($rowProperties);

                /** @var Collection $variants */
                $variants = $rows->filter(function (array $rowProperties, $rowIndex) use ($productRow) {
                    $variantRow = $this->getProductRow($rowProperties, $rowIndex);
                    return $variantRow['product_sku'] === $productRow['product_sku'];
                })->map(function (array $rowProperties, $rowIndex) use ($productResource) {
                    $variantRow = $this->getProductRow($rowProperties, $rowIndex);
                    return collect($variantRow)->filter(fn ($value, $key) => Str::contains($key, ['_variant', '_option', '_images']));
                });

                $images = $variants->pluck('product_images')->toArray();
                $productRow['variants'] = $variants->toArray();
                $productRow['product_images'] = $images;

                ProductSync::dispatch($productRow);
                // ProductSync::dispatchSync($productRow);
            });
    }

    protected function getProductRow(array $rowProperties, ?int $variantIndex = null): array
    {
        return collect($rowProperties)
            ->flatMap(fn ($value, $key) => [Str::slug($key, '_') => $value])
            ->filter(fn ($value, $key) => $productResource->field_map[$key] ?? false)
            ->flatMap(function ($value, $key) use ($rowProperties, $variantIndex) {
                $field = $productResource->field_map[$key];
                if (in_array($field, ['product_feature', 'product_option', 'product_images', 'product_collections'])) {
                    if (in_array($field, ['product_feature', 'product_option', 'product_collections'])) {
                        $field .= '_'.$key;
                    }
                    $value = Str::of($value)->contains(',')
                        ? Str::of($value)->explode(',')->map(fn ($value) => trim($value))
                        : [$value];
                }

                $rowKey = $rowProperties['Base SKU'];
                if ($variantIndex) {
                    $rowKey = $rowProperties['Base SKU'].'_'.$variantIndex;
                }

                if (Str::endsWith($key, $this->languageIsoCodes) && is_string($value)) {
                    $langIso = Str::afterLast($key, '_');
                    $this->fieldTranslations[$rowKey][$field][$langIso] ??= $value;
                    return $this->fieldTranslations[$rowKey];
                }

                return [$field => $value];
            })
            ->flatMap(function ($value, $key) use ($rowProperties, $variantIndex) {

                $rowKey = $rowProperties['Base SKU'];
                if ($variantIndex) {
                    $rowKey = $rowProperties['Base SKU'].'_'.$variantIndex;
                }

                if (Str::endsWith($key, $this->languageIsoCodes) && is_array($value)) {
                    $langIso = Str::afterLast($key, '_');
                    $fieldName = Str::of($key)->beforeLast('_'.$langIso)->value();

                    if (!$value[0] && !str_starts_with($key, 'name')) {
                        $defaultTranslation = $this->fieldTranslations[$rowKey][$fieldName]['en'] ?? null;
                        if ($defaultTranslation) {
                            $defaultTranslation .= ' ('.$langIso.')';
                            $value[0] = $defaultTranslation;
                        }
                    }

                    $this->fieldTranslations[$rowKey][$fieldName][$langIso] ??= $value[0] ?? $value;
                    return [$fieldName => $this->fieldTranslations[$rowKey][$fieldName]];
                }

                return [$key => $value];
            })
            ->toArray();
    }

    public function setTranslationsArray($value): array
    {
        return collect($value)->map(fn ($value, $key) => [$key => $value])->toArray();
    }
}
