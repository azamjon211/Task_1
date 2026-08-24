<?php

namespace Tests\Feature;

use App\Models\BatchRefund;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderRefund;
use App\Models\StockMovement;
use App\Services\RefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\InteractsWithInventory;
use Tests\TestCase;

class RefundLedgerTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithInventory;

    private RefundService $refunds;

    protected function setUp(): void
    {
        parent::setUp();
        $this->refunds = new RefundService();
    }

    public function test_batch_refund_writes_a_negative_movement_and_restores_nothing_it_shouldnt(): void
    {
        [, $product, $storage] = $this->makeProviderProductAndStorage();
        $batch = $this->makeBatch();
        $item = $this->makeBatchItem($batch, $product, $storage, ['qty' => 10, 'remaining_qty' => 10]);

        $refund = $this->refunds->refundSingleBatchItem(['batch_item_id' => $item->id, 'qty' => 3]);

        $item->refresh();
        $this->assertSame(7, $item->remaining_qty);
        $this->assertSame(3, $item->refunded_qty);
        $this->assertInstanceOf(BatchRefund::class, $refund);

        $movement = StockMovement::sole();
        $this->assertSame(StockMovement::TYPE_BATCH_REFUND, $movement->type);
        $this->assertSame(-3, $movement->qty);
        $this->assertSame($product->id, $movement->product_id);
        $this->assertSame($storage->id, $movement->storage_id);
        $this->assertSame($refund->id, $movement->source_id);
    }

    public function test_batch_refund_rejects_qty_above_remaining_and_writes_no_movement(): void
    {
        [, $product, $storage] = $this->makeProviderProductAndStorage();
        $batch = $this->makeBatch();
        $item = $this->makeBatchItem($batch, $product, $storage, ['qty' => 5, 'remaining_qty' => 5]);

        $this->expectException(\DomainException::class);

        try {
            $this->refunds->refundSingleBatchItem(['batch_item_id' => $item->id, 'qty' => 10]);
        } finally {
            $this->assertSame(5, $item->fresh()->remaining_qty);
            $this->assertSame(0, StockMovement::count());
        }
    }

    public function test_batch_refund_rejects_non_positive_qty(): void
    {
        [, $product, $storage] = $this->makeProviderProductAndStorage();
        $batch = $this->makeBatch();
        $item = $this->makeBatchItem($batch, $product, $storage, ['qty' => 5, 'remaining_qty' => 5]);

        $this->expectException(\Exception::class);

        try {
            $this->refunds->refundSingleBatchItem(['batch_item_id' => $item->id, 'qty' => 0]);
        } finally {
            $this->assertSame(0, StockMovement::count());
        }
    }

    public function test_refund_batch_rolls_back_every_line_when_one_line_is_invalid(): void
    {
        [, $product, $storage] = $this->makeProviderProductAndStorage();
        $batch = $this->makeBatch();
        $validItem = $this->makeBatchItem($batch, $product, $storage, ['qty' => 5, 'remaining_qty' => 5]);
        $invalidItem = $this->makeBatchItem($batch, $product, $storage, ['qty' => 2, 'remaining_qty' => 2]);

        try {
            $this->refunds->refundBatch([
                ['batch_item_id' => $validItem->id, 'qty' => 2],
                ['batch_item_id' => $invalidItem->id, 'qty' => 99],
            ]);
            $this->fail('Expected a DomainException for the second (invalid) line.');
        } catch (\DomainException $e) {
            // expected
        }

        // The whole batch of refund lines is one transaction: the first (valid)
        // line must NOT be committed just because a later line failed.
        $this->assertSame(5, $validItem->fresh()->remaining_qty);
        $this->assertSame(2, $invalidItem->fresh()->remaining_qty);
        $this->assertSame(0, StockMovement::count());
        $this->assertSame(0, BatchRefund::count());
    }

    public function test_order_refund_writes_a_positive_movement_and_restores_batch_item_stock(): void
    {
        [, $product, $storage] = $this->makeProviderProductAndStorage();
        $batch = $this->makeBatch();
        $batchItem = $this->makeBatchItem($batch, $product, $storage, ['qty' => 10, 'remaining_qty' => 4]); // 6 already sold
        $client = $this->makeClient();
        $order = Order::create(['client_id' => $client->id]);
        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'batch_item_id' => $batchItem->id,
            'storage_id' => $storage->id,
            'qty' => 6,
            'sale_price' => 20,
        ]);

        $refund = $this->refunds->refundOrder([
            ['order_item_id' => $orderItem->id, 'qty' => 2],
        ])->first();

        $this->assertSame(6, $batchItem->fresh()->remaining_qty);
        $this->assertSame(2, $orderItem->fresh()->refunded_qty);
        $this->assertInstanceOf(OrderRefund::class, $refund);

        $movement = StockMovement::sole();
        $this->assertSame(StockMovement::TYPE_ORDER_REFUND, $movement->type);
        $this->assertSame(2, $movement->qty);
        $this->assertSame($product->id, $movement->product_id);
        $this->assertSame($storage->id, $movement->storage_id);
        $this->assertSame($refund->id, $movement->source_id);
    }

    public function test_order_refund_rejects_qty_above_refundable_amount(): void
    {
        [, $product, $storage] = $this->makeProviderProductAndStorage();
        $batch = $this->makeBatch();
        $batchItem = $this->makeBatchItem($batch, $product, $storage, ['qty' => 10, 'remaining_qty' => 4]);
        $client = $this->makeClient();
        $order = Order::create(['client_id' => $client->id]);
        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'batch_item_id' => $batchItem->id,
            'storage_id' => $storage->id,
            'qty' => 6,
            'sale_price' => 20,
            'refunded_qty' => 5,
        ]);

        $this->expectException(\DomainException::class);

        try {
            // only 1 unit is refundable (qty 6 - refunded_qty 5)
            $this->refunds->refundOrder([
                ['order_item_id' => $orderItem->id, 'qty' => 2],
            ]);
        } finally {
            $this->assertSame(4, $batchItem->fresh()->remaining_qty);
            $this->assertSame(5, $orderItem->fresh()->refunded_qty);
            $this->assertSame(0, StockMovement::count());
        }
    }
}
