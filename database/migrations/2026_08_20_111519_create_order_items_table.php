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
        if (!Schema::hasTable('order_items')) {
            Schema::create('order_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();

                $table->foreignId('batch_item_id')->constrained('batch_items')->cascadeOnDelete();
                $table->foreignId('storage_id')->constrained('storages')->cascadeOnDelete();

                $table->unsignedInteger('qty');
                $table->decimal('sale_price', 12, 2);
                $table->unsignedInteger('refunded_qty')->default(0);

                $table->index(['batch_item_id']);

                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
