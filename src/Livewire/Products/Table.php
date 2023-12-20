<?php

namespace XtendLunar\Addons\StoreImporter\Livewire\Products;

use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Livewire\Component;
use XtendLunar\Addons\StoreImporter\Models\Product;

class Table extends Component implements HasTable
{
    use InteractsWithTable;

    public function getTableQuery(): Builder|Relation
    {
        return Product::query();
    }

    public function getTableColumns(): array
    {
        return [
            ImageColumn::make('imageLink'),
            TextColumn::make('title'),
            TextColumn::make('reference'),
            BadgeColumn::make('gender'),
            BadgeColumn::make('material'),
            BadgeColumn::make('product_types'),
            BadgeColumn::make('color'),
            BadgeColumn::make('sizes'),
            TextColumn::make('gmc_id'),
            TextColumn::make('price'),
            TextColumn::make('availability'),
        ];
    }

    public function getTableActions(): array
    {
        return [
            ViewAction::make()->url(fn($record) => $record->link)->openUrlInNewTab(),
        ];
    }

    public function render()
    {
        return view('adminhub::livewire.components.tables.base-table')
            ->layout('adminhub::layouts.base');
    }
}
