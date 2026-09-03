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
        // stock_movements.amount is signed so it can be profit itself: purchase/order_refund are
        // negative (cost / money paid back), sale/batch_refund are positive (revenue / money recovered).
        // batch_id is stamped on every movement type, including sales and order refunds, tracing back
        // to whichever batch_item the stock actually came from (FIFO) - so this needs no joins at all.
        $totals = DB::table('stock_movements')
            ->whereNotNull('batch_id')
            ->selectRaw("
                batch_id,
                -SUM(CASE WHEN type IN ('purchase', 'batch_refund') THEN amount ELSE 0 END) as cost,
                SUM(CASE WHEN type IN ('sale', 'order_refund') THEN amount ELSE 0 END) as revenue,
                SUM(amount) as profit
            ")
            ->groupBy('batch_id');

        $data = DB::table('batches')
            ->joinSub($totals, 'totals', 'totals.batch_id', '=', 'batches.id')
            ->select('batches.id as batch_id', 'batches.provider_id', 'batches.purchased_at', 'totals.cost', 'totals.revenue', 'totals.profit')
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
