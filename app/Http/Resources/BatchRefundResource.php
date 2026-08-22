<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BatchRefundResource extends JsonResource
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
            'batch_item_id' => $this->batch_item_id,
            'qty' => $this->qty,
            'amount' => (float)$this->amount,
            'reason' => $this->reason,
            'refunded_at' => $this->refunded_at?->toDateTimeString(),
        ];
    }
}
