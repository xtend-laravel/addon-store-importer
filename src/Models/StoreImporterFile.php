<?php

namespace XtendLunar\Addons\StoreImporter\Models;

use XtendLunar\Addons\StoreImporter\Enums\FileType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Class StoreImporterFile
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class StoreImporterFile extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = [];

	protected $casts = [
        'type' => FileType::class,
        'headers' => 'array',
        'settings' => 'array',
	];

	protected $table = 'xtend_store_importer_files';

	public function resources(): HasMany
    {
        return $this->hasMany(StoreImporterResource::class, 'file_id');
    }
}
