<?php

namespace XtendLunar\Addons\StoreImporter\Base\Transformers;

use Illuminate\Support\Collection;
use XtendLunar\Addons\StoreImporter\Integrations\TransformerInterface;

class VariantsTransformer implements TransformerInterface
{
    public function transform(Collection $data, \Closure $next): Collection
    {
        if (collect($data->get('options'))->isEmpty()) {
            return $next($data);
        }

        // @todo: implement this transformer.

        return $next($data);
    }
}
