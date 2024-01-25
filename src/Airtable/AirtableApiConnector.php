<?php

namespace XtendLunar\Addons\StoreImporter\Airtable;

use Saloon\Http\Auth\TokenAuthenticator;
use Saloon\Http\Connector;
use Saloon\Http\Request;
use Saloon\PaginationPlugin\Contracts\HasPagination;
use Saloon\PaginationPlugin\CursorPaginator;
use XtendLunar\Addons\StoreImporter\Airtable\Requests\AirtableCustomOffsetPaginator;

class AirtableApiConnector extends Connector implements HasPagination
{
    public function resolveBaseUrl(): string
    {
        return 'https://api.airtable.com/v0/' . config('store-importer.airtable.base_id');
    }

    protected function defaultAuth(): TokenAuthenticator
    {
        return new TokenAuthenticator(
            token: config('store-importer.airtable.access_token'),
        );
    }

    public function paginate(Request $request): CursorPaginator
    {
        return new AirtableCustomOffsetPaginator(
            connector: $this,
            request: $request,
        );
    }
}
