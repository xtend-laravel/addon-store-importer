<?php

namespace XtendLunar\Addons\StoreImporter\Livewire\Components\Forms;

use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\TemporaryUploadedFile;
use XtendLunar\Addons\StoreImporter\Enums\FileType;
use XtendLunar\Addons\StoreImporter\Enums\ResourceGroup;
use XtendLunar\Addons\StoreImporter\Enums\ResourceType;
use XtendLunar\Addons\StoreImporter\Jobs\ProductSync;
use XtendLunar\Addons\StoreImporter\Livewire\Components\Forms\Steps\FieldMapping;
use XtendLunar\Addons\StoreImporter\Livewire\Components\Forms\Steps\Import;
use XtendLunar\Addons\StoreImporter\Livewire\Components\Forms\Steps\Preview;
use XtendLunar\Addons\StoreImporter\Livewire\Components\Forms\Steps\Resources;
use XtendLunar\Addons\StoreImporter\Models\StoreImporterFile;
use XtendLunar\Addons\StoreImporter\Models\StoreImporterResource;
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

    protected string $view = 'adminhub::livewire.forms.wizard';

    protected Wizard $wizard;

    protected $listeners = [
        'upload:finished' => 'handleFileUploaded',
        'updateFieldMap' => 'updateFieldMap',
        'previousStep',
        'nextStep',
        'import',
    ];

    public function bootIfNotBooted()
    {
        $this->wizard = Wizard::make()
            ->steps([
                FieldMapping::class,
                Resources::class,
                Preview::class,
                Import::class,
            ]);
    }

    public function mount(): void
    {
        $this->model ??= StoreImporterFile::getModel();

        if ($this->model->exists) {
            $this->file = Storage::path($this->model->key);;
            $this->setFileImporterHeaders();
        }
    }

    public function previousStep(): void
    {
        $this->wizard->previousStep();
    }

    public function nextStep(): void
    {
        if ($this->wizard->currentStep() === 0) {
            $this->validate();
            $this->createResourcesFieldMap();
        }

        $this->wizard->nextStep();
    }

    public function import(): void
    {
        $this->fileImporter = resolve(FileImportInterface::class, [
            'file' => $this->file,
        ]);

        // @todo Move all this logic to its own class
        $rows = $this->fileImporter->getRows();
        $this->syncProductRows($rows);
    }

    protected function syncProductRows(Collection $rows): void
    {
        /** @var StoreImporterResource $productResource */
        $productResource = $this->model->resources()->where('type', ResourceType::Products)->sole();

        $rows
            ->filter(fn (array $rowProperties) => $rowProperties['Primary'] === 'checked')
            ->each(function(array $rowProperties) use ($productResource, $rows) {
                $productRow = $this->getProductRow($productResource, $rowProperties);

                $productRow['product_variants'] = $rows->filter(function (array $rowProperties) use ($productResource, $productRow) {
                    $variantRow = $this->getProductRow($productResource, $rowProperties);
                    return $variantRow['product_sku'] === $productRow['product_sku'];
                })->map(function (array $rowProperties) use ($productResource) {
                    $variantRow = $this->getProductRow($productResource, $rowProperties);
                    return $variantRow;
                });

                //ProductSync::dispatch($productResource, $productRow)->onQueue('store-importer');
                ProductSync::dispatchSync($productResource, $productRow);
            });
    }

    protected function getProductRow(StoreImporterResource $productResource, array $rowProperties): array
    {
        return collect($rowProperties)
            ->flatMap(fn ($value, $key) => [Str::slug($key, '_') => $value])
            ->filter(fn ($value, $key) => $productResource->field_map[$key] ?? false)
            ->flatMap(function ($value, $key) use ($productResource) {
                $field = $productResource->field_map[$key];
                if (in_array($field, ['product_feature', 'product_option', 'product_images', 'product_collections'])) {
                    if (in_array($field, ['product_feature', 'product_option', 'product_collections'])) {
                        $field .= '_'.$key;
                    }
                    $value = Str::of($value)->contains(',')
                        ? Str::of($value)->explode(',')->map(fn ($value) => trim($value))
                        : [$value];
                }
                return [$field => $value];
            })
            ->toArray();
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
            ...resolve($this->wizard->getSteps()[$this->wizard->currentStep()])->schema(),
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

    protected function fileResources(): Collection
    {
        return $this->model->resources;
    }
}
