<?php

namespace XtendLunar\Addons\StoreImporter\Base\Processors\Catalogue;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Lunar\FieldTypes\ListField;
use Lunar\FieldTypes\Text;
use Lunar\FieldTypes\TranslatedText;
use Lunar\Models\Attribute;
use Lunar\Models\Product as ProductModel;
use Lunar\Models\ProductType;
use XtendLunar\Addons\StoreImporter\Base\Processors\Processor;
use XtendLunar\Addons\StoreImporter\Models\StoreImporterResourceModel;

class Product extends Processor
{
    public function process(Collection $product, StoreImporterResourceModel $resourceModel): void
    {
        $productModel = ProductModel::updateOrCreate([
            'sku' => $product->get('sku'),
        ], [
            'attribute_data' => $this->getAttributeData($product),
            'product_type_id' => $this->getDefaultProductTypeId(),
            'status' => $product->get('status') ?? 'draft',
        ]);

	    $product->put('productModel', $productModel);

        $resourceModel->destination_model_type = Product::class;
        $resourceModel->destination_model_id = $productModel->id;
        $resourceModel->status = 'processing';
        $resourceModel->save();
    }

    protected function getAttributeData(Collection $product): array
    {
        /** @var Collection $attributes */
        $attributes = Attribute::whereAttributeType(Product::class)->get();

        $productAttributes = $product->filter(fn ($value, $field) => str_starts_with($field, 'attribute'))->mapWithKeys(
            fn ($value, $field) => [Str::afterLast($field, '.') => $value]
        );

        $attributeData = [];
        foreach ($productAttributes as $attributeHandle => $value) {
            $attribute = $attributes->first(fn ($att) => $att->handle == $attributeHandle);
            if (! $attribute) {
                continue;
            }

            if ($attribute->type == TranslatedText::class) {

                /* @var Collection $value */
                $value->transform(function (Text $text, string $locale) use ($value) {

                    // Make sure the first letter is uppercase & trim whitespace
                    $text->setValue(
                        ucfirst(trim($value->get($locale)->getValue()))
                    );

                    // @todo Better to perhaps replace with "Needs to be translated"
                    return $locale == 'en' && blank((string) $value->get('en'))
                        ? new Text((string) $value->get('fr'))
                        : $text;
                });

                $attributeData[$attributeHandle] = new TranslatedText($value);

                continue;
            }

            if ($attribute->type == ListField::class) {
                $attributeData[$attributeHandle] = new ListField((array) $value);
            }
        }

        return $attributeData;
    }

    protected function getDefaultProductTypeId(): int
    {
        return ProductType::first()->id;
    }
}
