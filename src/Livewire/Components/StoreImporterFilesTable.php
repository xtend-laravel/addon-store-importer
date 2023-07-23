<?php

namespace XtendLunar\Addons\StoreImporter\Livewire\Components;

use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Lunar\Hub\Http\Livewire\Traits\Notifies;
use XtendLunar\Addons\StoreImporter\Models\StoreImporterFile;
use XtendLunar\Features\FormBuilder\Livewire\Concerns\HasForms;

class StoreImporterFilesTable extends Component implements Tables\Contracts\HasTable
{
    use HasForms;
    use Notifies;
    use Tables\Concerns\InteractsWithTable;

    /**
     * {@inheritDoc}
     */
    protected function getTableQuery(): Builder
    {
        return StoreImporterFile::query();
    }

    /**
     * {@inheritDoc}
     */
    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\BadgeColumn::make('type'),
            Tables\Columns\TextColumn::make('name'),
            Tables\Columns\TextColumn::make('resources')
                ->getStateUsing(function ($record) {
                    $resources = $record->resources->pluck('type');
                    return $resources->map(fn ($resource) => $resource->name)->implode(' | ');
                }),
            Tables\Columns\TextColumn::make('created_at'),
            Tables\Columns\TextColumn::make('updated_at'),
            Tables\Columns\ViewColumn::make('progress')
                ->view('adminhub::components.progress-bar'),
        ];
    }

    /**
     * {@inheritDoc}
     */
    protected function getTableActions(): array
    {
        return [
            Tables\Actions\EditAction::make(),
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function mountTableAction(string $name, ?string $record = null): void
    {
        $model = StoreImporterFile::findOrFail($record);
        $this->triggerForm(
            handle: 'store-importer-file-form',
            model: $model,
            uiComponent: 'slideover',
        );
    }

    /**
     * Render the livewire component.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function render()
    {
        return view('adminhub::livewire.components.tables.base-table')
            ->layout('adminhub::layouts.base');
    }
}
