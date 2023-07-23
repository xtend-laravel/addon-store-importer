<?php

namespace XtendLunar\Addons\StoreImporter\Support\Import;

use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use Spatie\SimpleExcel\SimpleExcelReader;

class CsvImport implements FileImportInterface
{
    protected string $tempFilePath;

    protected SimpleExcelReader $reader;

    public function __construct(
        protected UploadedFile | string $file,
    ) {
        $this->reader = SimpleExcelReader::create(
            file: $this->tempFilePath(),
        );
    }

    protected function isUploadedFile(): bool
    {
        return $this->file instanceof UploadedFile;
    }

    protected function tempFilePath(): string
    {
        $data = $this->isUploadedFile()
            ? $this->file->get()
            : Storage::get($this->file);

        $extension = $this->isUploadedFile()
            ? $this->file->getClientOriginalExtension()
            : Str::afterLast($this->file, '.');

        $this->tempFilePath = tempnam(sys_get_temp_dir(), 'csv_import').'.'.$extension;
        file_put_contents($this->tempFilePath, $data);

        return $this->tempFilePath;
    }

    public function getHeaders(): array
    {
        return collect($this->reader->getHeaders() ?? [])
            ->flatMap(fn ($header) => [Str::slug($header, '_') => $header])->toArray();
    }

    public function getRows(): Collection
    {
        return $this->reader->getRows()->collect();
    }

    public function close(): void
    {
        if (file_exists($this->tempFilePath)) {
            unlink($this->tempFilePath);
        }
    }
}
