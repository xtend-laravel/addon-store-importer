<?php

namespace XtendLunar\Addons\StoreImporter\Integrations;

use Illuminate\Support\Collection;

interface TransformerInterface
{
    public function transform(Collection $data, \Closure $next): Collection;
}
