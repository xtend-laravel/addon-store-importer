<?php

namespace XtendLunar\Addons\StoreImporter\Airtable\Requests\Products;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\PaginationPlugin\Contracts\Paginatable;

class ListRecords extends Request implements Paginatable
{
    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return '/'. config('store-importer.airtable.table_name');
    }
}
