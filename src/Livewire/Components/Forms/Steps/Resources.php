<?php

namespace XtendLunar\Addons\StoreImporter\Livewire\Components\Forms\Steps;

use XtendLunar\Features\FormBuilder;
use XtendLunar\Addons\StoreImporter\Livewire\Components\Forms\StepInterface;

class Resources implements StepInterface
{
    public static int $step = 2;

    public function schema(): array
    {
        return [
            FormBuilder\Fields\FieldMap::make('fields')
                ->label('Field Map')
                ->required(),
        ];
    }
}
