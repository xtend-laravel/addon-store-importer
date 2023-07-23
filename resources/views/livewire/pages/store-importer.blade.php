<div class="flex-col space-y-4">
    <div class="flex items-center justify-between">
        <strong class="text-lg font-bold md:text-2xl">
            {{ __('Store Importer') }}
        </strong>
        <button
            class="ml-8 rounded border border-transparent bg-gray-100 px-4 py-2 text-xs font-bold text-gray-700 hover:border-gray-100 hover:bg-gray-50"
            type="button" wire:click.prevent="triggerForm('store-importer-file-form', null, 'slideover')">
            {{ __('adminhub::global.add') }}
        </button>
    </div>

    <div class="ui-slideover-forms">
        @each('adminhub::partials.ui.slideover', $this->slideoverForms ?? [], 'slideoverForm')
    </div>

    <div class="ui-modal-forms">
        @each('adminhub::partials.ui.modal', $this->modalForms ?? [], 'modalForm')
    </div>

    @livewire('hub.components.store-importer-files.table')
</div>
