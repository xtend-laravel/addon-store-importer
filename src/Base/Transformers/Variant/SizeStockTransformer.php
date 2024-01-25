<?php

namespace XtendLunar\Addons\StoreImporter\Base\Transformers\Variant;

use Illuminate\Support\Str;
use Lunar\FieldTypes\Text;
use Lunar\FieldTypes\TranslatedText;
use XtendLunar\Addons\StoreImporter\Base\Transformers\Transformer;

class SizeStockTransformer extends Transformer
{
    public function transform(array $productVariant): array
    {
        $productOptions = $productVariant['product_option'] ?? [];
        if (!$productOptions) {
            return $productVariant;
        }

        $sizes = array_map(
            fn($value) => 9999, array_flip($this->defaultSizes($productVariant)),
        );

        if ($productOptions['size'] ?? false) {
            $inStockSizes = Str::of($productOptions['size'])->explode(',')->mapWithKeys(
                function ($value) {
                    [$size, $stock] = explode(':', $value);
                    return [$size => (int)$stock];
                },
            )->toArray();

            $sizes = array_replace($sizes, $inStockSizes);
        }

        $productVariant['size'] = collect($sizes)->map(
            fn($size, $stock) => [
                'name' => new TranslatedText([
                    'en' => new Text($size),
                    'fr' => new Text($size),
                    'ar' => new Text($size),
                ]),
                'stock' => $stock,
            ],
        );

        return $productVariant;
    }

    protected function defaultSizes(array $productVariant): array
    {
        $collection = $productVariant['product_collections']['collections'][0] ?? null;

        if (!$collection) {
            throw new \Exception('No collection found for product variant.');
        }

        return match($collection) {
            'Men' => ['M', 'L', 'XL', 'XXL'],
            'Women' => ['S', 'M', 'L', 'XL', 'XXL', 'XXXL'],
            'Girls' => [4, 6, 8, 10, 12, 14],
            'Boys' => [4, 6, 8, 10, 12, 14],
        };
    }
}
