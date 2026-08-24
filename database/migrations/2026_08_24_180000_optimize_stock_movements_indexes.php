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
        Schema::table('stock_movements', function (Blueprint $table) {
            // remainingQuantities yagona so'rovi: WHERE happened_at <= ? GROUP BY storage_id, product_id, SUM(qty).
            // Bu composite index happened_at bo'yicha range-scan qiladi VA storage_id/product_id/qty'ni
            // o'zida saqlagani uchun so'rov butunlay indeks ichida bajariladi (asosiy jadvalga umuman tegilmaydi).
            $table->index(['happened_at', 'storage_id', 'product_id', 'qty'], 'stock_movements_report_covering_index');

            // storage_id ustidagi foreign key uchun kamida bitta index kerak (InnoDB talabi).
            $table->index('storage_id', 'stock_movements_storage_id_index');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex('stock_movements_happened_at_index');
            $table->dropIndex('stock_movements_storage_id_product_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->index(['happened_at']);
            $table->index(['storage_id', 'product_id'], 'stock_movements_storage_id_product_id_index');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex('stock_movements_report_covering_index');
            $table->dropIndex('stock_movements_storage_id_index');
        });
    }
};
