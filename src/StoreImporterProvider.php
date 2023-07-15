<?php

namespace XtendLunar\Addons\StoreImporter;

use CodeLabX\XtendLaravel\Base\XtendAddonProvider;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Events\LocaleUpdated;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Lunar\Hub\Facades\Menu;
use StoreImporter\Database\Seeders\StoreImporterSeeder;
use XtendLunar\Addons\StoreImporter\Livewire\Components\StoreImporterFilesTable;

class StoreImporterProvider extends XtendAddonProvider
{
    public function register()
    {
	    $this->loadRoutesFrom(__DIR__.'/../routes/hub.php');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'xtend-lunar::store-importer');
	    $this->loadViewsFrom(__DIR__.'/../resources/views', 'adminhub');
        $this->mergeConfigFrom(__DIR__.'/../config/store-importer.php', 'store-importer');
	    $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

		$this->registerSeeders([
			StoreImporterSeeder::class,
	    ]);
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
               // @todo Add commands to sync inventory, orders, etc.
            ]);
        }

	    Livewire::component('hub.components.store-importer-files.table', StoreImporterFilesTable::class);

	    $this->registerWithSidebarMenu();
    }

	protected function registerSeeders(array $seeders = []): void
	{
		$this->callAfterResolving(DatabaseSeeder::class, function (DatabaseSeeder $seeder) use ($seeders) {
			collect($seeders)->each(
				fn ($seederClass) => $seeder->call($seederClass),
			);
		});
	}

	protected function registerWithSidebarMenu(): void
	{
		Event::listen(LocaleUpdated::class, function () {
			Menu::slot('sidebar')
			    ->group('hub.configure')
			    ->section('hub.store-importer')
			    ->name('Store Importer')
			    ->route('hub.store-importer')
			    ->icon('database');
		});
	}
}
