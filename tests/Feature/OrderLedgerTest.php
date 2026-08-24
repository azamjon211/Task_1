<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\StockMovement;
use App\Services\OrderService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\InteractsWithInventory;
use Tests\TestCase;

class OrderLedgerTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithInventory;

    private OrderService $orders;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orders = new OrderService();
    }

    public function test_order_from_a_single_batch_writes_one_sale_movement(): void
    {
        [, $product, $storage] = $this->makeProviderProductAndStorage();
        $client = $this->makeClient();
        $batch = $this->makeBatch();
        $item = $this->makeBatchItem($batch, $product, $storage, ['qty' => 10]);

        $order = $this->orders->createOrder($client->id, [
            ['id' => $product->id, 'qty' => 4],
        ]);

        $this->assertSame(6, $item->fresh()->remaining_qty);

        $movements = StockMovement::where('type', StockMovement::TYPE_SALE)->get();
        $this->assertCount(1, $movements);

        $orderItem = $order->items->first();
        $movement = $movements->first();
        $this->assertSame(-4, $movement->qty);
        $this->assertSame($product->id, $movement->product_id);
        $this->assertSame($storage->id, $movement->storage_id);
        $this->assertSame($orderItem->id, $movement->source_id);
    }

    public function test_order_spanning_multiple_batches_writes_one_movement_per_batch_in_fifo_order(): void
    {
        [, $product, $storage] = $this->makeProviderProductAndStorage();
        $client = $this->makeClient();

        $oldBatch = $this->makeBatch(null, ['purchased_at' => '2026-01-01']);
        $oldItem = $this->makeBatchItem($oldBatch, $product, $storage, ['qty' => 5]);

        $newBatch = $this->makeBatch(null, ['purchased_at' => '2026-01-05']);
        $newItem = $this->makeBatchItem($newBatch, $product, $storage, ['qty' => 5]);

        // Requests 8: FIFO must drain the older batch (5) first, then take 3 from the newer one.
        $order = $this->orders->createOrder($client->id, [
            ['id' => $product->id, 'qty' => 8],
        ]);

        $this->assertSame(0, $oldItem->fresh()->remaining_qty);
        $this->assertSame(2, $newItem->fresh()->remaining_qty);

        $this->assertCount(2, $order->items);

        $movements = StockMovement::where('type', StockMovement::TYPE_SALE)
            ->orderBy('id')
            ->get();
        $this->assertCount(2, $movements);
        $this->assertSame(-5, $movements[0]->qty);
        $this->assertSame(-3, $movements[1]->qty);

        // Every sale movement must be traceable back to the order_item that caused it.
        $sourceIds = $movements->pluck('source_id')->sort()->values();
        $orderItemIds = $order->items->pluck('id')->sort()->values();
        $this->assertSame($orderItemIds->all(), $sourceIds->all());
    }

    public function test_order_with_multiple_products_ledgers_each_product_independently(): void
    {
        [$provider, $productA, $storage] = $this->makeProviderProductAndStorage();
        $category = $productA->category;
        $productB = $this->makeProduct($category);
        $client = $this->makeClient();

        $batch = $this->makeBatch($provider);
        $itemA = $this->makeBatchItem($batch, $productA, $storage, ['qty' => 10]);
        $itemB = $this->makeBatchItem($batch, $productB, $storage, ['qty' => 10]);

        $this->orders->createOrder($client->id, [
            ['id' => $productA->id, 'qty' => 3],
            ['id' => $productB->id, 'qty' => 7],
        ]);

        $this->assertSame(7, $itemA->fresh()->remaining_qty);
        $this->assertSame(3, $itemB->fresh()->remaining_qty);

        $this->assertSame(-3, StockMovement::where('product_id', $productA->id)->sole()->qty);
        $this->assertSame(-7, StockMovement::where('product_id', $productB->id)->sole()->qty);
    }

    public function test_duplicate_product_lines_in_the_same_request_are_merged_before_allocation(): void
    {
        [, $product, $storage] = $this->makeProviderProductAndStorage();
        $client = $this->makeClient();
        $batch = $this->makeBatch();
        $item = $this->makeBatchItem($batch, $product, $storage, ['qty' => 10]);

        // Same product listed twice (2 + 3): must be treated as a single request for 5.
        $order = $this->orders->createOrder($client->id, [
            ['id' => $product->id, 'qty' => 2],
            ['id' => $product->id, 'qty' => 3],
        ]);

        $this->assertSame(5, $item->fresh()->remaining_qty);
        $this->assertSame(-5, StockMovement::sole()->qty);
        $this->assertSame(5, $order->items->sum('qty'));
    }

    public function test_insufficient_stock_rolls_back_order_items_and_ledger_entries(): void
    {
        [, $product, $storage] = $this->makeProviderProductAndStorage();
        $client = $this->makeClient();
        $batch = $this->makeBatch();
        $item = $this->makeBatchItem($batch, $product, $storage, ['qty' => 3]);

        try {
            $this->orders->createOrder($client->id, [
                ['id' => $product->id, 'qty' => 10],
            ]);
            $this->fail('Expected a DomainException for insufficient stock.');
        } catch (\DomainException $e) {
            // expected
        }

        $this->assertSame(0, Order::count());
        $this->assertSame(0, OrderItem::count());
        $this->assertSame(0, StockMovement::count());
        $this->assertSame(3, $item->fresh()->remaining_qty);
    }

    public function test_when_the_second_product_fails_the_first_products_allocation_is_also_rolled_back(): void
    {
        [$provider, $productA, $storage] = $this->makeProviderProductAndStorage();
        $category = $productA->category;
        $productB = $this->makeProduct($category);
        $client = $this->makeClient();

        $batch = $this->makeBatch($provider);
        $itemA = $this->makeBatchItem($batch, $productA, $storage, ['qty' => 10]); // enough
        $itemB = $this->makeBatchItem($batch, $productB, $storage, ['qty' => 1]);  // NOT enough

        try {
            $this->orders->createOrder($client->id, [
                ['id' => $productA->id, 'qty' => 5],
                ['id' => $productB->id, 'qty' => 5],
            ]);
            $this->fail('Expected a DomainException for insufficient stock on product B.');
        } catch (\DomainException $e) {
            // expected
        }

        // Product A must NOT have been debited even though it had enough stock,
        // because the whole order is one atomic unit.
        $this->assertSame(10, $itemA->fresh()->remaining_qty);
        $this->assertSame(1, $itemB->fresh()->remaining_qty);
        $this->assertSame(0, OrderItem::count());
        $this->assertSame(0, StockMovement::count());
    }

    public function test_unknown_product_id_rolls_back_without_writing_any_ledger_entries(): void
    {
        [, $product, $storage] = $this->makeProviderProductAndStorage();
        $client = $this->makeClient();
        $batch = $this->makeBatch();
        $this->makeBatchItem($batch, $product, $storage, ['qty' => 10]);

        $missingId = $product->id + 999;

        $this->expectException(ModelNotFoundException::class);

        try {
            $this->orders->createOrder($client->id, [
                ['id' => $product->id, 'qty' => 1],
                ['id' => $missingId, 'qty' => 1],
            ]);
        } finally {
            $this->assertSame(0, Order::count());
            $this->assertSame(0, StockMovement::count());
        }
    }

    public function test_empty_product_list_is_rejected_before_any_query_runs(): void
    {
        $client = $this->makeClient();

        $this->expectException(\InvalidArgumentException::class);
        $this->orders->createOrder($client->id, []);
    }

    public function test_sequential_orders_exhaust_stock_and_a_later_order_fails_cleanly(): void
    {
        [, $product, $storage] = $this->makeProviderProductAndStorage();
        $client = $this->makeClient();
        $batch = $this->makeBatch();
        $item = $this->makeBatchItem($batch, $product, $storage, ['qty' => 5]);

        $this->orders->createOrder($client->id, [['id' => $product->id, 'qty' => 5]]);
        $this->assertSame(0, $item->fresh()->remaining_qty);

        try {
            $this->orders->createOrder($client->id, [['id' => $product->id, 'qty' => 1]]);
            $this->fail('Expected a DomainException: stock is fully exhausted.');
        } catch (\DomainException $e) {
            // expected
        }

        // Only the first, successful order should have left a trace.
        $this->assertSame(1, Order::count());
        $this->assertSame(1, StockMovement::count());
    }
}
