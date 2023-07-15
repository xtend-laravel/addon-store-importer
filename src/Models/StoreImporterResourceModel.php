<?php

namespace XtendLunar\Addons\StoreImporter\Models;

use XtendLunar\Addons\StoreImporter\Enums\ResourceModelStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class StoreImporterResourceModel
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class StoreImporterResourceModel extends Model
{
    use HasFactory;

    protected $guarded = [];

	protected $table = 'xtend_store_importer_resource_models';

	protected $casts = [
        'status' => ResourceModelStatus::class,
        'succeeded_at' => 'datetime',
		'failed_at' => 'datetime',
        'debug' => 'array',
	];

	public function resource(): BelongsTo
	{
		return $this->belongsTo(StoreImporterResource::class);
	}
}
