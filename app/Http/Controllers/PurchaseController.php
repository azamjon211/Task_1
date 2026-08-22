<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePurchaseRequest;
use App\Http\Resources\BatchResource;
use App\Services\PurchaseService;

class PurchaseController extends Controller
{
    public function __construct(private PurchaseService $purchases)
    {
    }

    // POST /api/purchases
    public function store(StorePurchaseRequest $request)
    {
        $providerId = (int) $request->input('provider_id');
        $purchasedAt = $request->input('purchased_at');
        $referenceNo = $request->input('reference_no');

        $lines = [];
        foreach ($request->input('products') as $p) {
            $lines[] = [
                'product_id' => $p['id'],
                'storage_id' => $p['storage_id'],
                'qty' => $p['qty'],
                'purchase_price' => $p['purchase_price'],
            ];
        }

        $batch = $this->purchases->purchase($providerId, $lines, $purchasedAt, $referenceNo);

        return new BatchResource($batch);
    }
}
