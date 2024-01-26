<?php

namespace XtendLunar\Addons\StoreImporter\Concerns;

use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Benchmark;
use Illuminate\Database\Eloquent\Model;
use XtendLunar\Addons\StoreImporter\Models\StoreImporterResourceModel;

/**
 * Trait InteractsWithDebug
 *
 * @property Closure|array $benchmarkables
 * @property array|null $result
 * @property StoreImporterResourceModel|Model $resourceModel
 */
trait InteractsWithDebug
{
    protected Closure|array $benchmarkables;

    protected ?array $result = [];

    protected function benchmark(Closure|array $benchmarkables, $iterations = 1, $logger = 'db'): self
    {
        $this->benchmarkables = $benchmarkables;

        $this->result = collect(Benchmark::measure(Arr::wrap($benchmarkables), $iterations))
            ->map(fn ($average) => number_format($average / 1000, 3).'s')
            ->when($benchmarkables instanceof Closure, fn ($c) => $c->first(), fn ($c) => $c->all());

        return $this;
    }

    protected function log(): Closure|array
    {
        // $this->resourceModel->update([
        //     'debug' => array_merge($this->resourceModel->debug ?? [], $this->result),
        // ]);

        return $this->benchmarkables;
    }
}
