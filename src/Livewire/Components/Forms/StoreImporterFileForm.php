<?php

namespace XtendLunar\Addons\StoreImporter\Livewire\Components\Forms;

use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Livewire\TemporaryUploadedFile;
use XtendLunar\Addons\StoreImporter\Enums\FileType;
use XtendLunar\Addons\StoreImporter\Enums\ResourceGroup;
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
        $this->model ??= StoreImporterFile::getModel();

        if ($this->model->exists) {
            $this->file = Storage::path($this->model->key);;
            $this->setFileImporterHeaders();
        }
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
            'fields' => 'array',
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

        $this->setFileImporterHeaders();

        if ($this->model->exists) {
            // @todo if the file is replaced then create new file importer
        }
    }

    protected function setFileImporterHeaders(): void
    {
        if (pathinfo($this->model->name, PATHINFO_EXTENSION) !== 'csv') {
            throw new \Exception('Currently only CSV files are supported.');
        }

        $this->fileImporter = resolve(FileImportInterface::class, [
            'file' => $this->file,
        ]);

        $this->headers = collect($this->fileImporter->getHeaders())->map(fn ($header, $key) => [
            'label' => $header,
            'value' => $this->model->exists ? $this->getMappedValue($key) : null,
        ])->toArray();

        $this->fields = $this->headers;
        $this->fileImporter->close();
    }

    protected function getMappedValue($key): ?string
    {
        return $this->fileResources()
            ->pluck('field_map')
            ->collapse()
            ->first(fn ($value, $field) => $field === $key);
    }

    public function create(): void
    {
        $this->validate();
        $fileKey = $this->file->storeAs('imports', $this->model->name);

        $this->model = $this->model->create([
            'name' => $this->model->name,
            'key' => $fileKey,
            'headers' => $this->headers,
            'type' => FileType::CSV->value,
        ]);

        $this->createResourcesFieldMap();

        $this->notify(
            message: __('Created :name import', ['name' => $this->model->name]),
            route: 'hub.store-importer',
        );
    }

    public function update(): void
    {
        $this->validate();
        $this->createResourcesFieldMap();

        $this->notify(
            message: __('Updated :name import', ['name' => $this->model->name]),
            route: 'hub.store-importer',
        );
    }

    protected function createResourcesFieldMap(): void
    {
        if ($this->fieldMap) {
            /** @var \Illuminate\Database\Eloquent\Relations\HasMany $resources */
            $resources = $this->model->resources();
            $resources->delete();
            $resources->createMany(
                collect($this->fieldMap)->map(fn ($fieldMap, $resourceType) => [
                    'group' => ResourceGroup::Core->value,
                    'type' => ResourceType::from($resourceType)->value,
                    'field_map' => $fieldMap,
                ])->toArray()
            );
        }
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
                'options' => [
                    'sku' => 'SKU',
                    'name' => 'Name',
                    'slug' => 'Slug',
                    'description' => 'Description',
                    'price' => 'Price',
                ],
            ],
            ResourceType::Categories->value => [
                'options' => [
                    'name' => 'Name',
                    'slug' => 'Slug',
                    'description' => 'Description',
                ],
            ],
            ResourceType::Brands->value => [
                'options' => [
                    'name' => 'Name',
                    'description' => 'Description',
                ],
            ],
            default => [],
        };
    }

    protected function fileResources(): Collection
    {
        return $this->model->resources;
    }
}
