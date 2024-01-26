<?php

namespace XtendLunar\Addons\StoreImporter\Base\Transformers\Variant;

use Illuminate\Support\Str;
use XtendLunar\Addons\StoreImporter\Base\Transformers\Transformer;

class FieldMapTransformer extends Transformer
{
    // @todo: This is a temporary solution, we need to either store this in a config file or define from the hub.
    private const FIELD_MAP = [
        'name' => 'product_name',
        'description' => 'product_description',
        'size' => 'product_option',
        'color' => 'product_option',
        'primary_color' => 'product_option',
        'price' => 'product_price_default',
        'style' => 'product_collections',
        'design' => 'product_feature',
        'fabric' => 'product_feature',
        'images' => 'product_images',
        'primary' => 'product_variant_primary',
        'base_sku' => 'product_sku',
        'weight' => 'product_weight',
        'featured' => 'product_collections',
        'collections' => 'product_collections',
    ];

    public function transform(array $data): array
    {
        $variant = collect($data['variant'])
            ->map(function ($value, $key) {
                dd($value, $key);
            })
            ->reduce(
                fn($carry, $value, $key) => $this->transformKey(value: $value, key: $key, product: $carry), [],
            );

        return collect($variant)
            ->only(self::FIELD_MAP)
            ->toArray();
    }

    private function transformKey($value, $key, $product): array
    {
        [$baseField, $baseFieldMapped] = $this->prepareMappedField($key);
        $extraField = Str::between($key, '(', ')');

        return $this->isExtraField($extraField, $key)
            ? $this->handleExtraField($baseField, $baseFieldMapped, $extraField, $value, $product)
            : $this->handleRegularField($baseField, $baseFieldMapped, $value, $product);
    }

    private function handleExtraField($baseField, $baseFieldMapped, $extraField, $value, $product): array
    {
        $this->isUniqueField($baseFieldMapped)
            ? $product[$baseFieldMapped][$extraField] = $value
            : $product[$baseFieldMapped][$baseField][$extraField] = $value;

        return $product;
    }

    private function handleRegularField($baseField, $baseFieldMapped, $value, $product): array
    {
        $baseFieldValue = self::FIELD_MAP[$baseField] ?? false;

        $this->isSplitStringValue($value)
            ? $product[$baseFieldMapped][$baseField] = explode(',', $value)
            : ($this->isUniqueField($baseFieldValue)
                ? ($product[$baseFieldMapped] = $value)
                : ($product[$baseFieldMapped][$baseField] = $value)
            );

        return $product;
    }

    private function isUniqueField($fieldName): bool
    {
        return collect(self::FIELD_MAP)
            ->filter(fn($value) => $value === $fieldName)
            ->count() === 1;
    }

    private function prepareMappedField($key) : array
    {
        $baseField = Str::of($key)->before(' (')->slug('_')->__toString();
        $mappedField = array_key_exists($baseField, self::FIELD_MAP) ? self::FIELD_MAP[$baseField] : $baseField;
        return [$baseField, $mappedField];
    }

    private function isExtraField($extraField, $key) : bool
    {
        return !empty($extraField) && Str::contains($key, ['(', ')']);
    }

    private function isSplitStringValue($value) : bool
    {
        return is_string($value) && str_contains($value, ',');
    }
}
