<?php

namespace XtendLunar\Addons\StoreImporter;

use Binaryk\LaravelRestify\Traits\InteractsWithRestifyRepositories;
use CodeLabX\XtendLaravel\Base\XtendAddonProvider;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Events\LocaleUpdated;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Lunar\Hub\Facades\Menu;
use StoreImporter\Database\Seeders\StoreImporterSeeder;
use XtendLunar\Addons\StoreImporter\Base\AirtableInterface;
use XtendLunar\Addons\StoreImporter\Base\AirtableManager;
use XtendLunar\Addons\StoreImporter\Commands\InventoryUpdateSync;
use XtendLunar\Addons\StoreImporter\Commands\SyncProductSeasons;
use XtendLunar\Addons\StoreImporter\Livewire\Products\Table;
use XtendLunar\Addons\StoreImporter\Enums\FileType;
use XtendLunar\Addons\StoreImporter\Livewire\Components\Forms\StoreImporterFileForm;
use XtendLunar\Addons\StoreImporter\Livewire\Components\StoreImporterFilesTable;
use XtendLunar\Addons\StoreImporter\Livewire\Pages\StoreImporter;
use XtendLunar\Addons\StoreImporter\Support\Import\CsvImport;
use XtendLunar\Addons\StoreImporter\Support\Import\FileImportInterface;
use XtendLunar\Addons\StoreImporter\Support\Import\JsonImport;

class StoreImporterProvider extends XtendAddonProvider
{
    use InteractsWithRestifyRepositories;

    protected $policies = [

    ];

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

            $file = $args['file'];
            $extension = is_string($file) ? pathinfo($file, PATHINFO_EXTENSION) : $file->extension();

            return match ($extension) {
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
                InventoryUpdateSync::class,
                SyncProductSeasons::class,
            ]);
        }

        if ($this->app->environment('production')) {
            $this->app->afterResolving(Schedule::class, function (Schedule $schedule) {
                // $schedule->command(SyncProducts::class)->dailyAt('00:00');
                // $schedule->command(DeleteProductsNotInChannel::class)->dailyAt('00:00');
            });
        }

        $this->registerPolicies();
	    $this->registerWithSidebarMenu();

        $this->publishes([
           __DIR__.'/../config/store-importer.php' => config_path('store-importer.php'),
        ]);

        $this->app->singleton(AirtableInterface::class, function ($app) {
            return $app->make(AirtableManager::class);
        });

        $this->registerLivewireComponents();
    }

    protected function registerLivewireComponents(): void
    {
        Livewire::component('hub.pages.store-importer', StoreImporter::class);
	    Livewire::component('hub.components.store-importer-files.table', StoreImporterFilesTable::class);
        Livewire::component('hub.components.forms.store-importer-file-form', StoreImporterFileForm::class);
        Livewire::component('hub.components.store-importer.products.table', Table::class);
    }

    protected function registerPolicies()
    {
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }
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
            Menu::slot('sidebar')
			    ->group('hub.configure')
			    ->section('hub.store-airtable-sync')
			    ->name('Airtable Sync')
			    ->route('hub.store-airtable-sync')
			    ->icon('database');
		});
	}
}
