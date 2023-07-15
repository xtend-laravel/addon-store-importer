<?php

namespace XtendLunar\Addons\StoreImporter\Models;

use XtendLunar\Addons\StoreImporter\Enums\ResourceType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Class StoreImporterResource
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class StoreImporterResource extends Model
{
    use HasFactory;

    protected $guarded = [];

	protected $casts = [
		'field_map' => 'array',
		'type' => ResourceType::class,
	];

	protected $table = 'xtend_store_importer_resources';

    public function models(): HasMany
    {
        return $this->hasMany(StoreImporterResourceModel::class, 'resource_id');
    }
}
