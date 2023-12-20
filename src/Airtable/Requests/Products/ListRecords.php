<?php

namespace XtendLunar\Addons\StoreImporter\Airtable\Requests\Products;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class ListRecords extends Request
{
    protected Method $method = Method::GET;

    protected function defaultQuery(): array
    {
        return [
            'maxRecords' => 1000,
        ];
    }

    public function resolveEndpoint(): string
    {
        return '/'. config('store-importer.airtable.table_name');
    }
}
