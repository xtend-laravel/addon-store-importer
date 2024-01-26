<?php

namespace XtendLunar\Addons\StoreImporter\Base\Processors;

use Illuminate\Support\Str;
use XtendLunar\Addons\StoreImporter\Concerns\InteractsWithDebug;
use XtendLunar\Addons\StoreImporter\Concerns\InteractsWithResourceModel;

abstract class Processor
{
    use InteractsWithDebug;
    use InteractsWithResourceModel;

    protected mixed $process;

    public function handle(mixed $passable, \Closure $next): mixed
    {
        $this->resourceModel = $passable['resourceModel'] ?? null;

        $processKey = Str::snake(class_basename($this), '-');
        $this->benchmark([
            $processKey => fn () => $this->process(...$passable),
        ])->log();

        return $next($passable);
    }
}
