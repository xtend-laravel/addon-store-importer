<?php

namespace XtendLunar\Addons\StoreImporter\Support\Import;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Spatie\SimpleExcel\SimpleExcelReader;

class CsvImport implements FileImportInterface
{
    protected string $tempFilePath;

    protected SimpleExcelReader $reader;

    public function __construct(
        protected UploadedFile $file,
    ) {
        $this->reader = SimpleExcelReader::create(
            file: $this->tempFilePath(),
        );
    }

    protected function tempFilePath(): string
    {
        $this->tempFilePath = tempnam(sys_get_temp_dir(), 'csv_import').'.'.$this->file->getClientOriginalExtension();
        file_put_contents($this->tempFilePath, $this->file->get());

        return $this->tempFilePath;
    }

    public function getHeaders(): array
    {
        return collect($this->reader->getHeaders() ?? [])
            ->flatMap(fn ($header) => [Str::slug($header, '_') => $header])->toArray();
    }
}
