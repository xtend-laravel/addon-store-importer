<?php

namespace XtendLunar\Addons\StoreImporter\Airtable\Concerns;

use Illuminate\Support\Collection;
use Lunar\Models\Product;
use XtendLunar\Addons\StoreImporter\Base\Airtable;

trait InteractsWithProducts
{
    protected Collection $products;

    protected function products(): self
    {
        $this->products = collect(Airtable::products());

        return $this;
    }

    protected function mapToSku(): self
    {
        $this->products = $this->products->mapWithKeys(
            fn($item) => [$item['fields']['Base SKU'] => $item['fields']],
        );

        return $this;
    }

    protected function importOnly(): self
    {
        $this->products = $this->products->filter(
            fn($item) => $item['fields']['Import Only'] ?? false,
        );

        return $this;
    }

    protected function style(string $style): self
    {
        $this->products = $this->products->filter(
            fn($item) => $item['fields']['Style'] === $style,
        );

        return $this;
    }

    protected function collection(string $collection): self
    {
        $this->products = $this->products->filter(
            fn($item) => in_array($collection, $item['fields']['Collections'] ?? [])
        );

        return $this;
    }

    protected function sku(string $sku): self
    {
        $this->products = $this->products->filter(
            fn($item) => $item['fields']['Base SKU'] === $sku,
        );

        return $this;
    }

    protected function get(): Collection
    {
        return $this->products;
    }

    protected function product(string $sku): Collection
    {
        return $this->products->get($sku);
    }

    protected function existsInStore(string $sku): bool
    {
        return Product::query()->firstWhere('sku', $sku)->exists();
    }
}
