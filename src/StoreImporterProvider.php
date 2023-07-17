<?php

namespace XtendLunar\Addons\StoreImporter;

use CodeLabX\XtendLaravel\Base\XtendAddonProvider;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Events\LocaleUpdated;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Livewire\TemporaryUploadedFile;
use Lunar\Hub\Facades\Menu;
use StoreImporter\Database\Seeders\StoreImporterSeeder;
use XtendLunar\Addons\StoreImporter\Enums\FileType;
use XtendLunar\Addons\StoreImporter\Livewire\Components\Forms\StoreImporterFileForm;
use XtendLunar\Addons\StoreImporter\Livewire\Components\StoreImporterFilesTable;
use XtendLunar\Addons\StoreImporter\Livewire\Pages\StoreImporter;
use XtendLunar\Addons\StoreImporter\Support\Import\CsvImport;
use XtendLunar\Addons\StoreImporter\Support\Import\FileImportInterface;
use XtendLunar\Addons\StoreImporter\Support\Import\JsonImport;

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

        $this->app->bind(FileImportInterface::class, function ($app, array $args) {
            /** @var File | TemporaryUploadedFile | UploadedFile $file */
            $file = $args['file'];
            return match ($file->extension()) {
                FileType::CSV->value => new CsvImport($file),
                FileType::JSON->value => new JsonImport($file),
                default => throw new \Exception('Invalid file type.'),
            };
        });
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
               // @todo Add commands to sync inventory, orders, etc.
            ]);
        }

        // @todo not sure why we need to register this here
	    Livewire::component('hub.pages.store-importer', StoreImporter::class);

	    Livewire::component('hub.components.store-importer-files.table', StoreImporterFilesTable::class);
        Livewire::component('hub.components.forms.store-importer-file-form', StoreImporterFileForm::class);

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
