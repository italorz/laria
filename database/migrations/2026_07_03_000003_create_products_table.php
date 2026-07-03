<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Produtos vinculados a uma região da imagem (hotspot).
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('post_id')->constrained()->cascadeOnDelete();
            $table->string('source_url', 2048);
            $table->string('title')->nullable();
            $table->string('price')->nullable();
            $table->string('image_url', 2048)->nullable();
            $table->float('pos_x')->default(0.5); // centro normalizado (0..1) do hotspot
            $table->float('pos_y')->default(0.5);
            $table->timestamps();

            $table->index('post_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
