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
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_id')->unique();
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->string('cashier_id'); // External POS cashier ID (can be UUID or string)
            $table->decimal('total_amount', 10, 2);
            $table->timestamp('sold_at');
            $table->enum('status', ['completed', 'cancelled', 'partially_cancelled'])->default('completed');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
