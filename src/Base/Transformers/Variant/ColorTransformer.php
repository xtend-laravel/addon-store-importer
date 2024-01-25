<?php

namespace XtendLunar\Addons\StoreImporter\Base\Transformers\Variant;

use Lunar\FieldTypes\Text;
use Lunar\FieldTypes\TranslatedText;
use XtendLunar\Addons\StoreImporter\Base\Transformers\Transformer;

class ColorTransformer extends Transformer
{
    public function transform(array $productVariant): array
    {
        $productOptions = $productVariant['product_option'] ?? [];
        if (!$productOptions) {
            return $productVariant;
        }

        if (!isset($productOptions['primary_color'])) {
            throw new \Exception('No primary color found for product variant .'.$productVariant['product_sku']);
        }

        $productVariant['color'] = [
            'name' => new TranslatedText(
                collect($productOptions['primary_color'])
                    ->map(fn($value, $key) => new Text($value)),
                ),
            'color' => $productOptions['color'][0] ?? null,
            'primary_color' => $productOptions['color'][0] ?? null,
            'secondary_color' => $productOptions['color'][1] ?? null,
            'tertiary_color' => $productOptions['color'][2] ?? null,
        ];

        return $productVariant;
    }
}
