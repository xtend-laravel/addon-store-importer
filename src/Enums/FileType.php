<?php

namespace XtendLunar\Addons\StoreImporter\Enums;

enum FileType: string
{
    case CSV = 'csv';
    case JSON = 'json';
    case XML = 'xml';

    public static function getValues(): array
    {
        return array_values(self::cases());
    }
}
