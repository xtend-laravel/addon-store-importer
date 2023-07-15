<?php

use App\Enums\FileType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('xtend_store_importer_files', function (Blueprint $table) {
            $table->uuid();
			$table->string('name');
            $table->string('path');
            $table->enum('type', FileType::getValues())->default(FileType::CSV);
			$table->json('settings')->nullable();
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
        Schema::dropIfExists('xtend_store_importer_files');
    }
};
