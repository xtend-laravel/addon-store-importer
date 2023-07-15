<?php

namespace XtendLunar\Addons\StoreImporter\Concerns;

use Illuminate\Database\Eloquent\Model;
use XtendLunar\Addons\StoreImporter\Models\StoreImporterResourceModel;

trait InteractsWithResourceModel
{
    protected StoreImporterResourceModel|Model $resourceModel;

    public function getResourceModel(): StoreImporterResourceModel|Model
    {
        return $this->resourceModel;
    }

    public function setResourceModel(StoreImporterResourceModel|Model $resourceModel): void
    {
        $this->resourceModel = $resourceModel;
    }
}
