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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('barcode')->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->string('unit')->default('pcs'); // pcs, kg, litre, etc.
            $table->string('category')->nullable();
            $table->boolean('is_active')->default(true);
            $table->enum('status', ['new', 'processed', 'failed'])->default('new');
            $table->unsignedBigInteger('sequence_id')->nullable();
            $table->timestamps();

            // Add index for status and sequence_id
            $table->index('status');
            $table->index('sequence_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
