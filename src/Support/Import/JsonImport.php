<?php

namespace XtendLunar\Addons\StoreImporter\Support\Import;

use Illuminate\Http\UploadedFile;

class JsonImport implements FileImportInterface
{
    public function __construct(
        protected UploadedFile $file,
    ) {
        $this->headers = [];
    }

    public function getHeaders(): array
    {
        return [];
    }
}
