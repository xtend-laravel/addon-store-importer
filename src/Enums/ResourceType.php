<?php

namespace XtendLunar\Addons\StoreImporter\Enums;

enum ResourceType: string
{
    case Products = 'products';
    case Variants = 'variants';
    case Prices = 'prices';
    case Collections = 'collections';
    case Brands = 'brands';
    // case Orders = 'orders';
    // case Carts = 'carts';
    // case Customers = 'customers';
    // case Addresses = 'addresses';

    public static function getValues(): array
    {
        return array_column(self::cases(), 'value');
    }
}
