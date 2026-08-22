<?php

namespace App\Http\Controllers;

use App\Http\Requests\RefundBatchRequest;
use App\Http\Requests\RefundOrderRequest;
use App\Http\Resources\BatchRefundResource;
use App\Services\RefundService;

class RefundController extends Controller
{
    public function __construct(private RefundService $refundService)
    {
    }

    public function refundBatch(RefundBatchRequest $request)
    {
        $refund = $this->refundService->refundBatch($request->lines);
        return BatchRefundResource::collection($refund);
    }
    public function refundOrder(RefundOrderRequest $request)
    {
        $refund = $this->refundService->refundOrder($request->lines);
        return response()->json(['data' => $refund]);
    }
}
