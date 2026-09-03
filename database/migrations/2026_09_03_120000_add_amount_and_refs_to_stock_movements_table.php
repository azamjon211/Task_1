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
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->decimal('amount', 12, 2)->nullable()->after('qty');
            $table->foreignId('batch_id')->nullable()->after('type')->constrained('batches')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->after('batch_id')->constrained('orders')->nullOnDelete();
            $table->index('batch_id');
            $table->index('order_id');
        });

        $this->backfillPurchases();
        $this->backfillBatchRefunds();
        $this->backfillSales();
        $this->backfillOrderRefunds();
    }

    /**
     * Backfill runs row-by-row in PHP (not a raw UPDATE...JOIN) because it has to
     * work identically on MySQL (dev) and SQLite (tests), which don't share join-update syntax.
     * Volumes here are inventory-ledger sized, not analytics-table sized, so this is cheap.
     */
    private function backfillPurchases(): void
    {
        DB::table('stock_movements as sm')
            ->join('batch_items as bi', 'bi.id', '=', 'sm.source_id')
            ->where('sm.type', 'purchase')
            ->select('sm.id', 'bi.batch_id', 'bi.purchase_price', 'sm.qty')
            ->orderBy('sm.id')
            ->get()
            ->each(function ($row) {
                DB::table('stock_movements')->where('id', $row->id)->update([
                    'batch_id' => $row->batch_id,
                    'amount' => -1 * $row->qty * $row->purchase_price,
                ]);
            });
    }

    private function backfillBatchRefunds(): void
    {
        DB::table('stock_movements as sm')
            ->join('batch_refunds as br', 'br.id', '=', 'sm.source_id')
            ->join('batch_items as bi', 'bi.id', '=', 'br.batch_item_id')
            ->where('sm.type', 'batch_refund')
            ->select('sm.id', 'bi.batch_id', 'br.amount')
            ->orderBy('sm.id')
            ->get()
            ->each(function ($row) {
                DB::table('stock_movements')->where('id', $row->id)->update([
                    'batch_id' => $row->batch_id,
                    'amount' => $row->amount,
                ]);
            });
    }

    private function backfillSales(): void
    {
        DB::table('stock_movements as sm')
            ->join('order_items as oi', 'oi.id', '=', 'sm.source_id')
            ->join('batch_items as bi', 'bi.id', '=', 'oi.batch_item_id')
            ->where('sm.type', 'sale')
            ->select('sm.id', 'bi.batch_id', 'oi.order_id', 'oi.qty', 'oi.sale_price')
            ->orderBy('sm.id')
            ->get()
            ->each(function ($row) {
                DB::table('stock_movements')->where('id', $row->id)->update([
                    'batch_id' => $row->batch_id,
                    'order_id' => $row->order_id,
                    'amount' => $row->qty * $row->sale_price,
                ]);
            });
    }

    private function backfillOrderRefunds(): void
    {
        DB::table('stock_movements as sm')
            ->join('order_refunds as orf', 'orf.id', '=', 'sm.source_id')
            ->join('order_items as oi', 'oi.id', '=', 'orf.order_item_id')
            ->join('batch_items as bi', 'bi.id', '=', 'oi.batch_item_id')
            ->where('sm.type', 'order_refund')
            ->select('sm.id', 'bi.batch_id', 'oi.order_id', 'orf.amount')
            ->orderBy('sm.id')
            ->get()
            ->each(function ($row) {
                DB::table('stock_movements')->where('id', $row->id)->update([
                    'batch_id' => $row->batch_id,
                    'order_id' => $row->order_id,
                    'amount' => -1 * $row->amount,
                ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropForeign(['batch_id']);
            $table->dropForeign(['order_id']);
            $table->dropColumn(['amount', 'batch_id', 'order_id']);
        });
    }
};
