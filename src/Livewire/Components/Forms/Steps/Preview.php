<?php

namespace XtendLunar\Addons\StoreImporter\Livewire\Components\Forms\Steps;

use XtendLunar\Features\FormBuilder;
use XtendLunar\Addons\StoreImporter\Livewire\Components\Forms\StepInterface;

class Preview implements StepInterface
{
    public static int $step = 3;

    public function schema(): array
    {
        return [
            FormBuilder\Fields\FieldMap::make('fields')
                ->label('Field Map')
                ->required(),
        ];
    }
}
