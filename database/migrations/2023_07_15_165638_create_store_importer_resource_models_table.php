<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use XtendLunar\Addons\StoreImporter\Enums\ResourceModelStatus;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('xtend_store_importer_resource_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_id')
                ->constrained('xtend_store_importer_resources')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

			$table->morphs('model');
	        $table->enum('status', ResourceModelStatus::getValues())->default(ResourceModelStatus::Pending->value);
            $table->timestamp('succeeded_at')->nullable();
			$table->timestamp('failed_at')->nullable();
			$table->string('failed_reason')->nullable();
            $table->json('debug')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('xtend_store_importer_resource_models');
    }
};
