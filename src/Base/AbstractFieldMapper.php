<?php

namespace XtendLunar\Addons\StoreImporter\Integrations;

use Illuminate\Support\Collection;

abstract class AbstractFieldMapper
{
    protected array $localeMap = [];

    protected string $translationKey;

    public function __construct(
        protected Collection $data,
        protected string $entity,
        protected string $source,
        protected string $destination,
    ) {}

    public function getFieldsByResource(): Collection
    {
        // @todo: implement this method.
        return collect();
    }

    public function map(): Collection
    {
        $data = $this->data
            ->filter(fn ($value, $key): bool => $this->getFieldsByResource()->has($key))
            ->merge(['fieldMapper' => $this->toArray()]);

        return $data;
    }

    public function toArray(): array
    {
        return [
            'localeMap' => $this->localeMap,
            'translationKey' => $this->translationKey,
            'translatableAttributes' => $this->translatableAttributes()[$this->entity],
            'fieldsByResource' => $this->getFieldsByResource(),
        ];
    }

    abstract public function translatableAttributes(): array;
}
