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
        Schema::create('inventory_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id'); // No constraint for events
            $table->unsignedBigInteger('branch_id'); // No constraint for events
            $table->enum('type', ['added', 'removed', 'adjusted']);
            $table->decimal('quantity', 10, 3);
            $table->decimal('previous_quantity', 10, 3);
            $table->decimal('new_quantity', 10, 3);
            $table->string('reason')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->enum('status', ['new', 'processed', 'failed'])->default('new');
            $table->unsignedBigInteger('sequence_id')->nullable();
            $table->timestamps();

            // Add indexes for better query performance
            $table->index('product_id');
            $table->index('branch_id');
            $table->index('status');
            $table->index('sequence_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_history');
    }
};
