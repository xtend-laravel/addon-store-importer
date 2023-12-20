<?php

namespace XtendLunar\Addons\StoreImporter\Base;

use Lunar\Models\Collection;
use Lunar\Models\Language;
use Lunar\Models\Product;
use Lunar\Models\ProductOptionValue;
use Lunar\Models\ProductVariant;
use Lunar\Models\Url;

class AirtableMapping
{
    public function __construct(
        protected Product $product,
    ) {}

    public static function make(Product $product): static
    {
        return new static($product);
    }

    public function map(): array
    {
        /** @var \Lunar\Models\ProductVariant $baseVariant */
        $baseVariant = $this->product->variants->filter(fn($variant) => $variant->base)->sole();

        return [
            'brand' => 'Awraaf',
            'channel' => 'online',
            'condition' => 'new',
            'ageGroup' => 'adult',
            'gender' => $this->getGender(),
            'productTypes' => $this->getProductTypes(),
            'targetCountry' => 'US',
            'contentLanguage' => 'en',
            'offerId' => $baseVariant->sku,
            'title' => $this->product->translateAttribute('name').' | '.$this->getColor(),
            'description' => $this->product->translateAttribute('description'),
            'link' => $this->getProductLink(),
            'imageLink' => $this->product->thumbnail->getUrl(),
            'additionalImageLinks' => $this->product->images
                ->filter(fn($image) => $image->id !== $this->product->thumbnail->id)
                ->map(fn($image) => $image->getUrl())
                ->values()
                ->toArray(),
            'availability' => $this->product->stock > 0 ? 'in stock' : 'preorder',
            'price' => collect([
                'value' => ($this->product->prices->first()->price->value / 100),
                'currency' => 'USD',
            ]),
            'sizes' => $this->getSizes(),
            'color' => $this->getColor(),
        ];
    }

    protected function getGender(): string
    {
        $collections = $this->product->collections;
        $collection = $collections->first(
            fn(Collection $collection) => $collection->group()->where('handle', 'categories')->exists()
        )->translateAttribute('name');

        return $collection === 'Women' ? 'female' : 'male';
    }

    protected function getProductTypes(): array
    {
        /** @var \Illuminate\Support\Collection $collections */
        $collections = $this->product->collections;
        $categories = $collections->map(
            fn(Collection $collection) => $collection->translateAttribute('name'),
        );

        return $categories->toArray();
    }

    protected function getProductLink(): string
    {
        $categorySlug = Url::query()->firstWhere([
            'element_id' => $this->product->primary_category_id,
            'element_type' => Collection::class,
        ])->slug ?? '--';

        $productSlug = $this->product->urls->firstWhere('language_id', Language::getDefault()->id)->slug;

        return 'https://awraaf.com/'.$categorySlug.'/'.$productSlug;
    }

    protected function getSizes(): string
    {
        return $this->getGender() === 'female'
            ? 'XS:XXXL'
            : 'M:XXL';
    }

    protected function getColor(): string
    {
        $colors = $this->getAvailableColors();

        return implode(', ', $colors);
    }

    protected function getAvailableColors(): array
    {
        return $this->product
            ->variants()
            ->where('base', false)
            ->get()
            ->flatMap(fn (ProductVariant $variant) => $variant->values->map(fn (ProductOptionValue $value) => $value->pivot->value_id))
            ->unique()
            ->values()
            ->filter(fn ($valueId) => ProductOptionValue::find($valueId)->option->name->en === 'Color')
            ->map(fn ($valueId) => ProductOptionValue::find($valueId)->name->en)
            ->unique()
            ->values()
            ->toArray();
    }
}
