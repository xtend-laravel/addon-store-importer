<?php

namespace XtendLunar\Addons\StoreImporter\Enums;

enum ResourceModelStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';

    public static function getValues(): array
    {
        return array_values(self::cases());
    }
}
