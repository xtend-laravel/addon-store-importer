<?php

namespace XtendLunar\Addons\StoreImporter\Base\Transformers;

abstract class Transformer
{
    public function handle(array $passable, \Closure $next): array
    {
        return $next($this->transform($passable));
    }
}
