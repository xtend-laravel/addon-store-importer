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

    public function process(Collection $product, StoreImporterResourceModel $resourceModel): void
    {
        $this->setProductModel($product->get('productModel'));

        $collections = collect($product->get('collections'))->map(
            fn (array $collections, $group) => collect($collections)->filter()->map(
                fn (string $collection) => $this->getCollection($collection, $group)
            )
        )->flatten();

        if ($collections->isNotEmpty()) {
            $this->getProductModel()->collections()->sync($collections->pluck('id'));
            $this->getProductModel()->primary_category_id = $collections->pluck('id')->first() ?? null;
            $this->getProductModel()->save();
            $this->getProductModel()->refresh();

            $productModel = $this->getProductModel();
            $product->put('productModel', $productModel);
        }
    }

    protected function getCollection(string $collection, string $group): CollectionModel
    {
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
