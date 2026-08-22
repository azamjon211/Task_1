<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function remainingQuantities(Request $request)
    {
        $request->validate(['date' => ['required', 'date']]);
        $date = $request->date('date')->endOfDay();

        $balances = [];
        $apply = function ($rows, int $sign) use (&$balances) {
            foreach ($rows as $row) {
                $key = $row->storage_id . ':' . $row->product_id;
                $balances[$key] = ($balances[$key] ?? 0) + $sign * $row->qty;
            }
        };

        // storagega - kirim uchun
        $apply(DB::table('batch_items')
            ->join('batches', 'batches.id', '=', 'batch_items.batch_id')
            ->where('batches.purchased_at', '<=', $date)
            ->selectRaw('batch_items.storage_id, batch_items.product_id, SUM(batch_items.qty) as qty')
            ->groupBy('batch_items.storage_id', 'batch_items.product_id')
            ->get(), 1);

        // providerga qaytarilganlar uchun
        $apply(DB::table('batch_refunds')
            ->join('batch_items', 'batch_items.id', '=', 'batch_refunds.batch_item_id')
            ->where('batch_refunds.refunded_at', '<=', $date)
            ->selectRaw('batch_items.storage_id, batch_items.product_id, SUM(batch_refunds.qty) as qty')
            ->groupBy('batch_items.storage_id', 'batch_items.product_id')
            ->get(), -1);

        // mijozga sotilganlar uchun
        $apply(DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.created_at', '<=', $date)
            ->selectRaw('order_items.storage_id, order_items.product_id, SUM(order_items.qty) as qty')
            ->groupBy('order_items.storage_id', 'order_items.product_id')
            ->get(), -1);

        // mijozdan qaytgan uchun
        $apply(DB::table('order_refunds')
            ->join('order_items', 'order_items.id', '=', 'order_refunds.order_item_id')
            ->where('order_refunds.refunded_at', '<=', $date)
            ->selectRaw('order_items.storage_id, order_items.product_id, SUM(order_refunds.qty) as qty')
            ->groupBy('order_items.storage_id', 'order_items.product_id')
            ->get(), 1);

        $result = [];
        foreach ($balances as $key => $qty) {
            [$storageId, $productId] = explode(':', $key);
            $result[] = [
                'storage_id' => (int) $storageId,
                'product_id' => (int) $productId,
                'qty' => $qty,
            ];
        }

        return response()->json(['data' => $result]);
    }

    public function batchProfit()
    {
        $cost = DB::table('batch_items')
            ->join('batches', 'batches.id', '=', 'batch_items.batch_id')
            ->selectRaw('batches.id as batch_id, SUM(batch_items.qty * batch_items.purchase_price) as total')
            ->groupBy('batches.id')
            ->pluck('total', 'batch_id');

        $batchRefundAmount = DB::table('batch_refunds')
            ->join('batch_items', 'batch_items.id', '=', 'batch_refunds.batch_item_id')
            ->selectRaw('batch_items.batch_id, SUM(batch_refunds.amount) as total')
            ->groupBy('batch_items.batch_id')
            ->pluck('total', 'batch_id');

        $revenue = DB::table('order_items')
            ->join('batch_items', 'batch_items.id', '=', 'order_items.batch_item_id')
            ->selectRaw('batch_items.batch_id, SUM(order_items.qty * order_items.sale_price) as total')
            ->groupBy('batch_items.batch_id')
            ->pluck('total', 'batch_id');

        $orderRefundAmount = DB::table('order_refunds')
            ->join('order_items', 'order_items.id', '=', 'order_refunds.order_item_id')
            ->join('batch_items', 'batch_items.id', '=', 'order_items.batch_item_id')
            ->selectRaw('batch_items.batch_id, SUM(order_refunds.amount) as total')
            ->groupBy('batch_items.batch_id')
            ->pluck('total', 'batch_id');

        $data = Batch::all()->map(function (Batch $batch) use ($cost, $batchRefundAmount, $revenue, $orderRefundAmount) {
            $totalCost = (float) ($cost[$batch->id] ?? 0) - (float) ($batchRefundAmount[$batch->id] ?? 0);
            $totalRevenue = (float) ($revenue[$batch->id] ?? 0) - (float) ($orderRefundAmount[$batch->id] ?? 0);

            return [
                'batch_id' => $batch->id,
                'provider_id' => $batch->provider_id,
                'purchased_at' => $batch->purchased_at?->toDateString(),
                'cost' => $totalCost,
                'revenue' => $totalRevenue,
                'profit' => $totalRevenue - $totalCost,
            ];
        });

        return response()->json(['data' => $data]);
    }
}
