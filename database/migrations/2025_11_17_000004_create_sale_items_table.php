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
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->onDelete('cascade');
            $table->string('product_id'); // External product ID (not FK)
            $table->string('unit');
            $table->decimal('unit_price', 10, 2);
            $table->boolean('is_cancelled')->default(false);
            $table->timestamps();

            // Add indexes for better query performance
            $table->index(['sale_id', 'product_id']);
            $table->index('is_cancelled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
