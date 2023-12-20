<?php

namespace XtendLunar\Addons\StoreImporter\Livewire\Pages;

use Livewire\Component;

class AirtableIndex extends Component
{
    public function render()
    {
        return view('adminhub::livewire.pages.airtable-products.index')
            ->layout('adminhub::layouts.app');
    }
}
