<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use XtendLunar\Addons\StoreImporter\Enums\ResourceGroup;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('xtend_store_importer_resources', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('file_id')
                ->constrained('xtend_store_importer_files')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

			$table->string('name');
            $table->enum('type', ResourceGroup::getValues())->default(ResourceGroup::Core->value);
			$table->json('field_map')->nullable();
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
        Schema::dropIfExists('xtend_store_importer_resources');
    }
};
