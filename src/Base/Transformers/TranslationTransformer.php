<?php

namespace XtendLunar\Addons\StoreImporter\Base\Transformers;

use Illuminate\Support\Collection;
use Lunar\FieldTypes\Text;
use XtendLunar\Addons\StoreImporter\Integrations\TransformerInterface;

class TranslationTransformer implements TransformerInterface
{
    public function transform(Collection $data, \Closure $next): Collection
    {
        $localeMap = $data['fieldMapper']['localeMap'];
        $translationKey = $data['fieldMapper']['translationKey'] ?? 'value';
        if (! $localeMap) {
            return $data;
        }

        $data = $data->merge(
            $data->filter(fn ($value, $field) => in_array($field, $data['fieldMapper']['translatableAttributes'] ?? []))
                 ->mapWithKeys(
                    fn ($value, $key) => [
                        $key => collect($value)->flatMap(
                            fn ($value, $key) => [$localeMap[$key] => new Text($value[$translationKey]) ?? $value]
                        ),
                    ]
                )
            );

        return $next($data);
    }
}
