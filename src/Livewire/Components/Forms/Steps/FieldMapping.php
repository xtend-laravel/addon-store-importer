<?php

namespace XtendLunar\Addons\StoreImporter\Livewire\Components\Forms\Steps;

use XtendLunar\Addons\StoreImporter\Enums\ResourceType;
use XtendLunar\Features\FormBuilder;
use XtendLunar\Addons\StoreImporter\Livewire\Components\Forms\StepInterface;

class FieldMapping implements StepInterface
{
    public static int $step = 1;

    public function schema(): array
    {
        return [
            FormBuilder\Fields\Fileupload::make('key')
                ->label('CSV Input File')
                ->required(),
            FormBuilder\Fields\FieldMap::make('fields')
                ->label('Field Map')
                ->fieldMap($this->getMappedResourceFieldGroups())
                ->required(),
        ];
    }

    protected function getMappedResourceFieldGroups(): array
    {
        return collect(ResourceType::cases())
            ->mapWithKeys(fn (ResourceType $resourceType) => [
                $resourceType->name => $this->getMappedResourceFields($resourceType),
            ])->toArray();
    }

    protected function getMappedResourceFields(ResourceType $resourceType): array
    {
        return match ($resourceType->value) {
            ResourceType::Products->value => [
                'options' => [
                    'product_brand_id' => 'Brand Association (id / name)',
                    'product_type_id' => 'Product Type Association (id / name)',
                    'product_sku' => 'Base SKU',
                    'product_variant_sku' => 'Variant SKU',
                    'product_variant_price' => 'Variant Price',
                    'product_variant_primary' => 'Variant Primary',
                    'product_price_default' => 'Price Default',
                    'product_feature' => 'Product Feature',
                    'product_option' => 'Product Option',
                    'product_images' => 'Images (comma separated)',
                    'product_collections' => 'Collections (comma separated)',
                    'product_name' => 'Name',
                    'product_description' => 'Description',
                    'product_status' => 'Status',
                ],
            ],
            ResourceType::Variants->value => [
                'options' => [
                    'variant_product_id' => 'Product Association (id / name)',
                    'variant_tax_class_id' => 'Tax Class Association (id / name)',
                    'variant_base' => 'Base',
                    'variant_primary' => 'Price',
                    'variant_unit_quantity' => 'Unit Quantity',
                    'variant_sku' => 'SKU',
                    'variant_stock' => 'Stock',
                    'variant_backorder' => 'Backorder',
                ],
            ],
            ResourceType::Prices->value => [
                'options' => [
                    'prices_price' => 'Price',
                ],
            ],
            ResourceType::Collections->value => [
                'options' => [
                    'collection_parent_id' => 'Parent Association (id / name)',
                    'collection_name' => 'Name',
                    'collection_description' => 'Description',
                ],
            ],
            ResourceType::Brands->value => [
                'options' => [
                    'brand_name' => 'Name',
                ],
            ],
            default => [],
        };
    }
}
