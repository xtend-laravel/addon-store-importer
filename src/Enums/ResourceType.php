<?php

namespace XtendLunar\Addons\StoreImporter\Enums;

enum ResourceType: string
{
    case Products = 'products';
    case Categories = 'categories';
    case Brands = 'brands';
    case ProductOptions = 'product_options';
    case ProductFeatures = 'product_features';
    case Orders = 'orders';
    case Carts = 'carts';
    case Customers = 'customers';
    case Addresses = 'addresses';
    case Pages = 'pages';

    public static function getValues(): array
    {
        return array_values(self::cases());
    }
}
