<?php

namespace XtendLunar\Addons\StoreImporter\Livewire\Pages;

use Illuminate\View\View;
use Livewire\Component;
use Lunar\Hub\Http\Livewire\Traits\Notifies;
use XtendLunar\Features\FormBuilder\Livewire\Concerns\HasForms;

class StoreImporter extends Component
{
    use HasForms, Notifies;

    public function render(): View
    {
        return view('adminhub::livewire.pages.store-importer')
            ->layout('adminhub::layouts.app', [
                'title' => __('Store Importer'),
            ]);
    }
}
