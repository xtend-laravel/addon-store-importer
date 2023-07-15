<?php

namespace XtendLunar\Addons\StoreImporter\Base\Transformers;

use Illuminate\Support\Collection;
use XtendLunar\Addons\StoreImporter\Integrations\TransformerInterface;

class FieldMapKeyTransformer implements TransformerInterface
{
    public function transform(Collection $data, \Closure $next): Collection
    {
        $fieldMap = $data['fieldMapper']['fieldsByEntity'];

        return $next(
            $data->except(['fieldMapper'])
                 ->filter(fn ($value, $field) => $fieldMap->has($field))
                 ->mapWithKeys(fn ($value, $field) => [$fieldMap->get($field) => $value])
        );
    }
}
