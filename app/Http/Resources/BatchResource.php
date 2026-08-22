<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BatchResource extends JsonResource
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
            'provider_id' => $this->provider_id,
            'purchased_at' => $this->purchased_at?->toDateString(),
            'reference_no' => $this->reference_no,
            'items' => $this->whenLoaded('items', fn() => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->relationLoaded('product') ? $item->product->name : null,
                'storage_id' => $item->storage_id,
                'storage_name' => $item->relationLoaded('storage') ? $item->storage->name : null,
                'qty' => $item->qty,
                'purchase_price' => (float) $item->purchase_price,
                'remaining_qty' => $item->remaining_qty,
            ])),
        ];
    }
}
