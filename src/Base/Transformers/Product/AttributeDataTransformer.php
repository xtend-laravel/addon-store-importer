<?php

namespace XtendLunar\Addons\StoreImporter\Base\Transformers\Product;

use Lunar\FieldTypes\Text;
use Lunar\FieldTypes\TranslatedText;
use XtendLunar\Addons\StoreImporter\Base\Transformers\Transformer;

class AttributeDataTransformer extends Transformer
{
    private const TRANSLATABLE_FIELDS = [
        'product_name' => 'name',
        'product_description' => 'description',
    ];

    public function transform(array $product):array
    {
        $product['attribute_data'] = collect($product)
            ->only(array_keys(self::TRANSLATABLE_FIELDS))
            ->mapWithKeys(
                fn($value, $key) => [
                    self::TRANSLATABLE_FIELDS[$key] => new TranslatedText(
                        collect($value)->map(fn($value, $key) => new Text($value)),
                    ),
                ],
            );

        return collect($product)
            ->except(array_keys(self::TRANSLATABLE_FIELDS))
            ->toArray();
    }
}
