<?php

namespace XtendLunar\Addons\StoreImporter\Models;

use Illuminate\Database\Eloquent\Model;
use Sushi\Sushi;
use XtendLunar\Addons\StoreImporter\Base\Airtable;

class Product extends Model
{
    use Sushi;

    public function getRows(): array
    {
        return collect(Airtable::products())
            ->map(fn($item) => $this->formatProduct($item))
            ->toArray();
    }

    private function formatProduct(array $item): array
    {
        return [
            'gmc_id' => $item['id'],
            'title' => $item['title'],
            'description' => $item['description'],
            'material' => $item['material'] ?? null,
            'imageLink' => $item['imageLink'] ?? null,
            'reference' => strtoupper($item['offerId']),
            'availability' => $item['availability'],
            'price' => $item['price']['value'],
            'product_types' => $this->getProductTypes($item),
            'gender' => ucwords($item['gender'] ?? 'unisex'),
            'color' => $item['color'] ?? null,
            'sizes' => implode(',', $item['sizes'] ?? []),
            'link' => $item['link'] ?? null,
        ];
    }

    private function getProductTypes(array $item): string
    {
        return implode(',', $item['productTypes'] ?? ['Clothing']);
    }
}
