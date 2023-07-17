<?php

namespace XtendLunar\Addons\StoreImporter\Enums;

enum ResourceGroup: string
{
    case Core = 'core';
    case Addon = 'addon';
    case Feature = 'feature';
    case Custom = 'custom';

    public static function getValues(): array
    {
        return array_column(self::cases(), 'value');
    }
}
