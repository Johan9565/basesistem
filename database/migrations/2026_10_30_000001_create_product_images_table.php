<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->string('company_id')->index();
            $table->string('product_id')->index();
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->unsignedTinyInteger('order')->default(1);
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_processed')->default(false);
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamps();

            // Índices compuestos útiles para búsquedas rápidas en MongoDB / SQL
            $table->index(['product_id', 'order']);
            $table->index(['product_id', 'is_primary']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};

