<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(private OrderService $orderService)
    {
    }
    public function store(StoreOrderRequest $request){
        $order = $this->orderService->createOrder(
            (int)$request->client_id,
            $request->products,
        );
        return new OrderResource($order);
    }
}
