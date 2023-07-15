<?php

namespace XtendLunar\Addons\StoreImporter\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use XtendLunar\Addons\StoreImporter\Concerns\InteractsWithDebug;
use XtendLunar\Addons\StoreImporter\Concerns\InteractsWithPipeline;
use XtendLunar\Addons\StoreImporter\Concerns\InteractsWithResourceModel;
use XtendLunar\Addons\StoreImporter\Enums\ResourceModelStatus;

class InventorySync implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithDebug;
    use InteractsWithPipeline;
    use InteractsWithResourceModel;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @var Collection
     */
    protected Collection $resource;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(
        protected int $resourceId
    ) {}

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->benchmark([
            'prepare' => fn() => $this->prepare(),
            'sync' => fn() => $this->resource->isNotEmpty()
                ? DB::transaction(fn() => $this->sync())
                : null,
        ])->log();
    }

    protected function prepare(): void
    {
        $this->benchmark([
            'prepare.collections' => fn() => $this->prepareCollections(),
            'prepare.resource' => fn() => $this->prepareProducts(),
            'prepare.variants' => fn() => $this->prepareVariants(),
            'prepare.images' => fn() => $this->prepareProductImages(),
        ])->log();
    }

    protected function prepareCollections(): void
    {
        // @todo Implement this method.
    }

    protected function prepareProducts(): void
    {
        // @todo Implement this method.
    }

    protected function prepareVariants(): void
    {
        // @todo Implement this method.
    }

    protected function prepareProductImages(): void
    {
        // @todo Implement this method.
    }

    protected function sync(): void
    {
        $this->prepareThroughPipeline(
            passable: [
                'resource' => $this->resource,
                'resourceModel' => $this->resourceModel,
            ],
            pipes: [

            ],
        );

        $this->resourceModel->status = ResourceModelStatus::Created;
        $this->resourceModel->save();
    }
}
