<?php

namespace XtendLunar\Addons\StoreImporter\Base;

use XtendLunar\Addons\StoreImporter\Integrations\AbstractFieldMapper;

class FieldMapper extends AbstractFieldMapper
{
    // @todo Replace this with resource type enum.
    protected array $entities = [
        'products',
        'collections',
        'attributes',
        'categories',
        'brands',
        'variants',
        'orders',
        'customers',
        'carts',
    ];

    public function translatableAttributes(): array
    {
        // @todo: implement this method hard coded for now.
        return [
            'products' => [
                'attribute.name',
                'attribute.description',
            ],
            'collections' => [
                'name',
                'description',
            ],
        ];
    }
}
