<?php

namespace XtendLunar\Addons\StoreImporter\Livewire\Components\Forms;

use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\TemporaryUploadedFile;
use Lunar\Models\Language;
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

    public array $fieldTranslations = [];

    public array $languageIsoCodes = [];

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

        $this->notify(
            message: __('Updated :name import', ['name' => $this->model->name]),
            route: 'hub.store-importer',
        );
    }

    protected function syncProductRows(Collection $rows): void
    {
        /** @var StoreImporterResource $productResource */
        $productResource = $this->model->resources()->where('type', ResourceType::Products)->sole();

        $rows
            //->filter(fn (array $rowProperties) => $rowProperties['Base SKU'] === 'AWR-GMTF-006')
            ->filter(fn (array $rowProperties) => $rowProperties['Primary'] === 'checked')
            ->each(function(array $rowProperties) use ($productResource, $rows) {
                $productRow = $this->getProductRow($productResource, $rowProperties);

                /** @var Collection $variants */
                $variants = $rows->filter(function (array $rowProperties, $rowIndex) use ($productResource, $productRow) {
                    $variantRow = $this->getProductRow($productResource, $rowProperties, $rowIndex);
                    return $variantRow['product_sku'] === $productRow['product_sku'];
                })->map(function (array $rowProperties, $rowIndex) use ($productResource) {
                    $variantRow = $this->getProductRow($productResource, $rowProperties, $rowIndex);
                    return collect($variantRow)->filter(fn ($value, $key) => Str::contains($key, ['_variant', '_option', '_images']));
                });

                $images = $variants->pluck('product_images')->toArray();
                $productRow['variants'] = $variants->toArray();
                $productRow['product_images'] = $images;

                ProductSync::dispatch($productResource, $productRow);
                // ProductSync::dispatchSync($productResource, $productRow);
            });
    }

    protected function getProductRow(StoreImporterResource $productResource, array $rowProperties, ?int $variantIndex = null): array
    {
        return collect($rowProperties)
            ->flatMap(fn ($value, $key) => [Str::slug($key, '_') => $value])
            ->filter(fn ($value, $key) => $productResource->field_map[$key] ?? false)
            ->flatMap(function ($value, $key) use ($productResource, $rowProperties, $variantIndex) {
                $field = $productResource->field_map[$key];
                if (in_array($field, ['product_feature', 'product_option', 'product_images', 'product_collections'])) {
                    if (in_array($field, ['product_feature', 'product_option', 'product_collections'])) {
                        $field .= '_'.$key;
                    }
                    $value = Str::of($value)->contains(',')
                        ? Str::of($value)->explode(',')->map(fn ($value) => trim($value))
                        : [$value];
                }

                $rowKey = $rowProperties['Base SKU'];
                if ($variantIndex) {
                    $rowKey = $rowProperties['Base SKU'].'_'.$variantIndex;
                }

                if (Str::endsWith($key, $this->languageIsoCodes) && is_string($value)) {
                    $langIso = Str::afterLast($key, '_');
                    $this->fieldTranslations[$rowKey][$field][$langIso] ??= $value;
                    return $this->fieldTranslations[$rowKey];
                }

                return [$field => $value];
            })
            ->flatMap(function ($value, $key) use ($rowProperties, $variantIndex) {

                $rowKey = $rowProperties['Base SKU'];
                if ($variantIndex) {
                    $rowKey = $rowProperties['Base SKU'].'_'.$variantIndex;
                }

                if (Str::endsWith($key, $this->languageIsoCodes) && is_array($value)) {
                    $langIso = Str::afterLast($key, '_');
                    $fieldName = Str::of($key)->beforeLast('_'.$langIso)->value();

                    if (!$value[0] && !str_starts_with($key, 'name')) {
                        $defaultTranslation = $this->fieldTranslations[$rowKey][$fieldName]['en'] ?? null;
                        if ($defaultTranslation) {
                            $defaultTranslation .= ' ('.$langIso.')';
                            $value[0] = $defaultTranslation;
                        }
                    }

                    $this->fieldTranslations[$rowKey][$fieldName][$langIso] ??= $value[0] ?? $value;
                    return [$fieldName => $this->fieldTranslations[$rowKey][$fieldName]];
                }

                return [$key => $value];
            })
            ->toArray();
    }

    public function setTranslationsArray($value): array
    {
        return collect($value)->map(fn ($value, $key) => [$key => $value])->toArray();
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
        $this->languageIsoCodes = Language::all()->pluck('code')->toArray();

        if (pathinfo($this->model->name, PATHINFO_EXTENSION) !== 'csv') {
            throw new \Exception('Currently only CSV files are supported.');
        }

        $this->fileImporter = resolve(FileImportInterface::class, [
            'file' => $this->file,
        ]);

        $this->headers = collect($this->fileImporter->getHeaders())->map(fn ($header, $key) => [
            'label' => $header,
            'value' => $this->model->exists
                ? $this->getMappedValue($key)
                : $this->getDefaultMappedValue($key),
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

    protected function getDefaultMappedValue($key): ?string
    {
        $fieldMap = collect([
            'name' => 'product_name',
            'description' => 'product_description',
            'size' => 'product_option',
            'color' => 'product_option',
            'primary_color' => 'product_option',
            'price' => 'product_price_default',
            'style' => 'product_collections',
            'design' => 'product_feature',
            'fabric' => 'product_feature',
            'images' => 'product_images',
            'primary' => 'product_variant_primary',
            'base_sku' => 'product_sku',
            'weight' => 'product_weight',
            'featured' => 'product_collections',
            'categories' => 'product_collections',
        ]);

        return $this->getMappedValue($key) ?? $fieldMap->first(function ($value, $field) use ($key) {
            if (Str::endsWith($key, $this->languageIsoCodes)) {
                $key = Str::of($key)->beforeLast('_')->value();
            }
            return $field === $key;
        });
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
