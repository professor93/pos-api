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
            $table->string('check_number')->unique();
            $table->string('store_id'); // External POS store ID (can be UUID or string)
            $table->string('cashier_id'); // External POS cashier ID (can be UUID or string)
            $table->decimal('total_amount', 10, 2);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('final_amount', 10, 2);
            $table->string('fiscal_sign')->nullable();
            $table->string('terminal_id')->nullable();
            $table->timestamp('sale_datetime');
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
