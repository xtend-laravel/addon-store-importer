<?php

namespace XtendLunar\Addons\StoreImporter\Base;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;
use Lunar\Models\Product;

/**
 * @method static array products()
 * @see AirtableManager::products
 *
 * @method static array modifyProduct(Product $product, string $productId)
 * @see AirtableManager::modifyProduct
 *
 * @method static array removeProduct(Product $product, string $productId)
 * @see AirtableManager::removeProduct
 *
 * @method static array removeAll(Collection $productIds)
 * @see AirtableManager::removeAll
 *
 * @method static array createProduct(Product $product)
 * @see AirtableManager::createProduct
 *
 * @see \XtendLunar\Addons\StoreImporter\Base\AirtableManager
 */
class Airtable extends Facade
{
    /**
     * {@inheritdoc}
     */
    protected static function getFacadeAccessor()
    {
        return AirtableInterface::class;
    }
}
