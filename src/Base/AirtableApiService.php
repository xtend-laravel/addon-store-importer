<?php

namespace XtendLunar\Addons\StoreImporter\Base;

use Saloon\Http\Request;
use XtendLunar\Addons\StoreImporter\Airtable\AirtableApiConnector;
use XtendLunar\Addons\StoreImporter\Airtable\Requests\Products\ListRecords;

class AirtableApiService
{
    public function __construct(
        private AirtableApiConnector $connector)
    {}

    public function fetchProducts(): array
    {
        return $this->processRequest(
            request: new ListRecords(),
        );
    }

    private function processRequest(Request $request): array
    {
        $response = $this->connector->send(
            request: $request,
        );

        dd($response->json());

        if ($response->failed()) {
            dump($response->json('error.message'));
            return [];
        }

        return $response->json('resources') ?? [];
    }
}
