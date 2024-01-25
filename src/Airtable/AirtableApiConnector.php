<?php

namespace XtendLunar\Addons\StoreImporter\Airtable;

use Saloon\Http\Auth\TokenAuthenticator;
use Saloon\Http\Connector;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\PaginationPlugin\Contracts\HasPagination;
use Saloon\PaginationPlugin\OffsetPaginator;

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

    public function paginate(Request $request): OffsetPaginator
    {
        return new class(connector: $this, request: $request) extends OffsetPaginator
        {
            protected ?int $perPageLimit = 100;

            protected function isLastPage(Response $response): bool
            {
                return $this->getOffset() >= (int)$response->json('total');
            }

            protected function getPageItems(Response $response, Request $request): array
            {
                return $response->json('items');
            }
        };
    }
}
