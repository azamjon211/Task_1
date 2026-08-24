<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function remainingQuantities(Request $request)
    {
        $request->validate(['date' => ['required', 'date']]);
        $date = $request->date('date')->endOfDay();

        $result = DB::table('stock_movements')
            ->where('happened_at', '<=', $date)
            ->selectRaw('storage_id, product_id, SUM(qty) as qty')
            ->groupBy('storage_id', 'product_id')
            ->get()
            ->map(fn ($row) => [
                'storage_id' => (int) $row->storage_id,
                'product_id' => (int) $row->product_id,
                'qty' => (int) $row->qty,
            ]);

        return response()->json(['data' => $result]);
    }

    public function batchProfit()
    {

        $cost = DB::table('batch_items')
            ->selectRaw('batch_id, SUM(qty * purchase_price) as total')
            ->groupBy('batch_id');


        $batchRefundAmount = DB::table('batch_refunds')
            ->join('batch_items', 'batch_items.id', '=', 'batch_refunds.batch_item_id')
            ->selectRaw('batch_items.batch_id, SUM(batch_refunds.amount) as total')
            ->groupBy('batch_items.batch_id');


        $revenue = DB::table('order_items')
            ->join('batch_items', 'batch_items.id', '=', 'order_items.batch_item_id')
            ->selectRaw('batch_items.batch_id, SUM(order_items.qty * order_items.sale_price) as total')
            ->groupBy('batch_items.batch_id');


        $orderRefundAmount = DB::table('order_refunds')
            ->join('order_items', 'order_items.id', '=', 'order_refunds.order_item_id')
            ->join('batch_items', 'batch_items.id', '=', 'order_items.batch_item_id')
            ->selectRaw('batch_items.batch_id, SUM(order_refunds.amount) as total')
            ->groupBy('batch_items.batch_id');


        $data = DB::table('batches')
            ->leftJoinSub($cost, 'costs', 'costs.batch_id', '=', 'batches.id')
            ->leftJoinSub($batchRefundAmount, 'batch_refund_totals', 'batch_refund_totals.batch_id', '=', 'batches.id')
            ->leftJoinSub($revenue, 'revenues', 'revenues.batch_id', '=', 'batches.id')
            ->leftJoinSub($orderRefundAmount, 'order_refund_totals', 'order_refund_totals.batch_id', '=', 'batches.id')
            ->selectRaw('
                batches.id as batch_id,
                batches.provider_id,
                batches.purchased_at,
                COALESCE(costs.total, 0) - COALESCE(batch_refund_totals.total, 0) as cost,
                COALESCE(revenues.total, 0) - COALESCE(order_refund_totals.total, 0) as revenue,
                (COALESCE(revenues.total, 0) - COALESCE(order_refund_totals.total, 0))
                    - (COALESCE(costs.total, 0) - COALESCE(batch_refund_totals.total, 0)) as profit
            ')
            ->get()
            ->map(fn ($row) => [
                'batch_id' => (int) $row->batch_id,
                'provider_id' => (int) $row->provider_id,
                'purchased_at' => $row->purchased_at,
                'cost' => (float) $row->cost,
                'revenue' => (float) $row->revenue,
                'profit' => (float) $row->profit,
            ]);


        return response()->json(['data' => $data]);
        }
}
