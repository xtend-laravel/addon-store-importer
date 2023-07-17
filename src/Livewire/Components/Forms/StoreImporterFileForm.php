<?php

namespace XtendLunar\Addons\StoreImporter\Livewire\Components\Forms;

use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Livewire\TemporaryUploadedFile;
use XtendLunar\Addons\StoreImporter\Enums\FileType;
use XtendLunar\Addons\StoreImporter\Enums\ResourceType;
use XtendLunar\Addons\StoreImporter\Models\StoreImporterFile;
use XtendLunar\Addons\StoreImporter\Support\Import\FileImportInterface;
use XtendLunar\Features\FormBuilder;

class StoreImporterFileForm extends FormBuilder\Base\LunarForm
{
    public string $layout = 'slideover';

    public array $fields = [];

    public array $fieldMap = [];

    public File | TemporaryUploadedFile | UploadedFile | string $file;

    public array $headers = [];

    protected FileImportInterface $fileImporter;

    protected $listeners = [
        'upload:finished' => 'handleFileUploaded',
        'updateFieldMap' => 'updateFieldMap',
    ];

    public function mount(): void
    {
        $this->model = StoreImporterFile::getModel();

        $this->model->fill([
            'file_type' => FileType::CSV,
            'fields' => [],
        ]);
    }

    public function updateFieldMap($fieldMap): void
    {
        $this->fieldMap = $fieldMap;
    }

    protected function rules(): array
    {
        return [
            'model.name' => 'required',
            'model.key' => 'required',
            'model.fields' => 'array',
            'fieldMap' => 'array',
        ];
    }

    protected function schema(): array
    {
        return [
            FormBuilder\Fields\Fileupload::make('key')
                ->label('CSV Input File')
                ->required(),
            FormBuilder\Fields\FieldMap::make('fields')
                ->label('Field Map')
                ->fieldMap($this->getMappedResourceFieldGroups())
                ->required(),
        ];
    }

    public function handleFileUploaded($name, array $filenames = []): void
    {
        $this->model->key = $filenames[0];
        $this->file = TemporaryUploadedFile::createFromLivewire($this->model->key);
        $this->model->name = $this->file->getClientOriginalName();

        $this->fileImporter = resolve(FileImportInterface::class, [
            'file' => $this->file,
        ]);

        $this->headers = $this->fileImporter->getHeaders();
        $this->model->fields = $this->headers;
    }

    public function create(): void
    {
        $this->validate();

        if (pathinfo($this->model->name, PATHINFO_EXTENSION) !== 'csv') {
            throw new \Exception('Currently only CSV files are supported.');
        }

        dd($this->fieldMap);

        $this->model->fill([
            'name' => $this->model->name,
            'key' => $this->file->storeAs('imports', $this->model->name),
            'headers' => $this->headers,
            'type' => FileType::CSV,
        ])->save();
    }

    protected function getMappedResourceFieldGroups(): array
    {
        return collect(ResourceType::cases())
            ->mapWithKeys(fn (ResourceType $resourceType) => [
                $resourceType->name => $this->getMappedResourceFields($resourceType),
            ])->toArray();
    }

    protected function getMappedResourceFields(ResourceType $resourceType): array
    {
        return match ($resourceType->value) {
            ResourceType::Products->value => [
                'sku' => 'SKU',
                'name' => 'Name',
                'slug' => 'Slug',
                'description' => 'Description',
                'price' => 'Price',
            ],
            ResourceType::Categories->value => [
                'name' => 'Name',
                'slug' => 'Slug',
                'description' => 'Description',
            ],
            ResourceType::Brands->value => [
                'name' => 'Name',
                'description' => 'Description',
            ],
            default => [],
        };
    }
}
