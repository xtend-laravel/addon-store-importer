<?php

namespace XtendLunar\Addons\StoreImporter\Base\Transformers\Product;

use Illuminate\Support\Str;
use Lunar\FieldTypes\Text;
use Lunar\FieldTypes\TranslatedText;
use XtendLunar\Addons\StoreImporter\Base\Transformers\Transformer;

class FeaturesTransformer extends Transformer
{
    public function transform(array $product):array
    {
        $product['product_feature'] = collect($product['product_feature'])
            ->mapWithKeys(
                function ($value, $key) {
                    return [
                        $key => [
                            'name' => new TranslatedText([
                                'en' => new Text(Str::headline($key)),
                                'fr' => new Text(Str::headline($key)),
                                'ar' => new Text(Str::headline($key)),
                            ]),
                            'handle' => $key,
                            'values' => new TranslatedText(
                                collect($value)->map(
                                    fn($value, $key) => new Text((string)(is_array($value) ? $value[0] : $value)),
                                ),
                            ),
                        ],
                    ];
                },
            );

        return $product;
    }
}
