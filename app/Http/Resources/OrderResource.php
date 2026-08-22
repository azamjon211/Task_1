<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'created_at' => $this->created_at?->toDateTimeString(),
            'items' => $this->whenLoaded('items', fn() => $this->items->map(fn ($item) =>[
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->relationLoaded('product')? $item->product->name: null,
                'batch_item_id' => $item->batch_item_id,
                'storage_id' => $item->storage_id,
                'qty'  => $item->qty,
                'sale_price' => (float)$item->sale_price,
                'refund_qty' => (float)$item->refunded_qty,
            ])),
            'total' => $this->whenLoaded('items', fn()=> (float)$this->items->sum(
                fn($item) => ($item->qty - $item->refunded_qty)* $item->sale_price)),
        ];
    }
}
