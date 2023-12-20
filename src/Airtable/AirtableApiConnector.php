<?php

namespace XtendLunar\Addons\StoreImporter\Airtable;

use Saloon\Http\Auth\TokenAuthenticator;
use Saloon\Http\Connector;

class AirtableApiConnector extends Connector
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
}
