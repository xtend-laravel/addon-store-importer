<?php

namespace XtendLunar\Addons\StoreImporter\Airtable\Requests;

use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\PaginationPlugin\CursorPaginator;

class AirtableCustomOffsetPaginator extends CursorPaginator
{
    protected ?int $perPageLimit = 100;

    protected function getNextCursor(Response $response): int|string
    {
        return $response->json('offset');
    }

    protected function isLastPage(Response $response): bool
    {
        return is_null($response->json('offset'));
    }

    protected function getPageItems(Response $response, Request $request): array
    {
        return $response->json('records');
    }

    protected function applyPagination(Request $request): Request
    {
        if ($this->currentResponse instanceof Response) {
            $request->query()->add('offset', $this->getNextCursor($this->currentResponse));
        }

        if (isset($this->perPageLimit)) {
            $request->query()->add('pageSize', $this->perPageLimit);
        }

        return $request;
    }
}
