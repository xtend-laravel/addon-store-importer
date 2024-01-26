<?php

namespace XtendLunar\Addons\StoreImporter\Base\Processors\Catalogue;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Lunar\FieldTypes\Text;
use Lunar\FieldTypes\TranslatedText;
use Lunar\Models\CollectionGroup;
use Xtend\Extensions\Lunar\Core\Models\Collection as CollectionModel;
use XtendLunar\Addons\StoreImporter\Base\Processors\Catalogue\Concerns\InteractsWithProductModel;
use XtendLunar\Addons\StoreImporter\Base\Processors\Processor;
use XtendLunar\Addons\StoreImporter\Models\StoreImporterResourceModel;

class Collections extends Processor
{
    use InteractsWithProductModel;

    public function process(Collection $product, ?StoreImporterResourceModel $resourceModel = null): void
    {
        $this->setProductModel($product->get('productModel'));

        $collections = $this->getCollections($product);

        if ($collections->isNotEmpty()) {
            $this->getProductModel()->collections()->sync($collections->pluck('id'));
            $this->getProductModel()->primary_category_id = $collections->pluck('id')->first() ?? null;
            $this->getProductModel()->save();
            $this->getProductModel()->refresh();

            $productModel = $this->getProductModel();
            $product->put('productModel', $productModel);
        }
    }

    protected function getCollections(Collection $product): Collection
    {
        return collect($product->get('product_collections') ?? $product->get('collections'))->map(
            fn (mixed $collections, $group) => collect(is_string($collections) ? [$collections] : $collections)->filter()->map(
                fn (string $collection) => $this->ensureCollectionExists($collection, $group)
            )
        )->flatten();
    }

    protected function ensureCollectionExists(string $collection, string $group): CollectionModel
    {
        if ($group === 'style') {
            $group = 'styles';
        }

        $collectionGroup = CollectionGroup::firstOrCreate([
            'name' => Str::headline($group),
            'handle' => $group,
        ]);

        $query = CollectionModel::query()
            ->whereJsonContains('attribute_data->name->value->en', $collection);

        /** @var Builder | CollectionModel $query */
        return $query->exists()
            ? $query->first()
            : CollectionModel::create([
                'collection_group_id' => $collectionGroup->id,
                'attribute_data' => [
                    'name' => new TranslatedText([
                        'en' => new Text($collection),
                    ]),
                ],
            ]);
    }
}
