<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('storage_id')->constrained('storages')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->integer('qty');
            $table->string('type');
            $table->unsignedBigInteger('source_id');
            $table->timestamp('happened_at');
            $table->timestamps();

            $table->index(['happened_at']);
            $table->index(['storage_id', 'product_id']);
        });


        $now = now();

        DB::statement("
            INSERT INTO stock_movements (storage_id, product_id, qty, type, source_id, happened_at, created_at, updated_at)
            SELECT bi.storage_id, bi.product_id, bi.qty, 'purchase', bi.id, b.purchased_at, ?, ?
            FROM batch_items bi
            JOIN batches b ON b.id = bi.batch_id
        ", [$now, $now]);

        DB::statement("
            INSERT INTO stock_movements (storage_id, product_id, qty, type, source_id, happened_at, created_at, updated_at)
            SELECT bi.storage_id, bi.product_id, -br.qty, 'batch_refund', br.id, br.refunded_at, ?, ?
            FROM batch_refunds br
            JOIN batch_items bi ON bi.id = br.batch_item_id
        ", [$now, $now]);

        DB::statement("
            INSERT INTO stock_movements (storage_id, product_id, qty, type, source_id, happened_at, created_at, updated_at)
            SELECT oi.storage_id, oi.product_id, -oi.qty, 'sale', oi.id, o.created_at, ?, ?
            FROM order_items oi
            JOIN orders o ON o.id = oi.order_id
        ", [$now, $now]);

        DB::statement("
            INSERT INTO stock_movements (storage_id, product_id, qty, type, source_id, happened_at, created_at, updated_at)
            SELECT oi.storage_id, oi.product_id, orf.qty, 'order_refund', orf.id, orf.refunded_at, ?, ?
            FROM order_refunds orf
            JOIN order_items oi ON oi.id = orf.order_item_id
        ", [$now, $now]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
