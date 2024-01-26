<?php

namespace XtendLunar\Addons\StoreImporter\Concerns;

use Xtend\Extensions\Lunar\Core\Models\Product;
use XtendLunar\Addons\StoreImporter\Enums\ResourceModelStatus;
use XtendLunar\Addons\StoreImporter\Models\StoreImporterResource;
use XtendLunar\Addons\StoreImporter\Models\StoreImporterResourceModel;

trait InteractsWithResourceModel
{
    protected ?StoreImporterResourceModel $resourceModel;

    /**
     * @throws \Exception
     */
    public function setResourceModelByKey(
        StoreImporterResource $resource,
        $queryKey = 'id',
        $queryValue = null,
    ): void
    {
        $model = match ($resource->type->value) {
            'products' => Product::query()->firstWhere($queryKey, $queryValue),
            default => throw new \Exception('Resource type not supported'),
        };

        $resourceModel = $model
            ? $resource->models()->firstOrCreate([
                'model_type' => $model->getMorphClass(),
                'model_id' => $model->getKey(),
            ])
            : $this->prepareResourceModel($resource);

        $this->resourceModel = $resourceModel;
    }

    protected function prepareResourceModel(StoreImporterResource $resource): StoreImporterResourceModel
    {
        $resource->models()->whereNull([
            'model_type',
            'model_id',
        ])->delete();

        return $resource->models()->create([
            'status' => ResourceModelStatus::Pending,
        ]);
    }

    public function getResourceModel(): StoreImporterResourceModel
    {
        return $this->resourceModel;
    }
}
