<?php

namespace Tests\Feature;

use App\Models\StockMovement;
use App\Services\OrderService;
use App\Services\PurchaseService;
use App\Services\RefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\InteractsWithInventory;
use Tests\TestCase;

class ReportControllerTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithInventory;

    public function test_remaining_quantities_only_counts_movements_up_to_the_given_date(): void
    {
        [, $product, $storage] = $this->makeProviderProductAndStorage();

        StockMovement::create([
            'storage_id' => $storage->id, 'product_id' => $product->id,
            'qty' => 10, 'type' => StockMovement::TYPE_PURCHASE, 'source_id' => 1,
            'happened_at' => '2026-01-01',
        ]);
        StockMovement::create([
            'storage_id' => $storage->id, 'product_id' => $product->id,
            'qty' => -4, 'type' => StockMovement::TYPE_SALE, 'source_id' => 1,
            'happened_at' => '2026-01-10',
        ]);
        StockMovement::create([
            'storage_id' => $storage->id, 'product_id' => $product->id,
            'qty' => -2, 'type' => StockMovement::TYPE_SALE, 'source_id' => 2,
            'happened_at' => '2026-02-01',
        ]);

        $this->getJson('/api/storages/remaining?date=2026-01-05')
            ->assertOk()
            ->assertJson(['data' => [
                ['storage_id' => $storage->id, 'product_id' => $product->id, 'qty' => 10],
            ]]);

        $this->getJson('/api/storages/remaining?date=2026-01-15')
            ->assertOk()
            ->assertJson(['data' => [
                ['storage_id' => $storage->id, 'product_id' => $product->id, 'qty' => 6],
            ]]);

        $this->getJson('/api/storages/remaining?date=2026-03-01')
            ->assertOk()
            ->assertJson(['data' => [
                ['storage_id' => $storage->id, 'product_id' => $product->id, 'qty' => 4],
            ]]);
    }

    public function test_remaining_quantities_groups_independently_per_storage_and_product(): void
    {
        [, $productA, $storage1] = $this->makeProviderProductAndStorage();
        $storage2 = $this->makeStorage();
        $productB = $this->makeProduct($productA->category);

        StockMovement::create([
            'storage_id' => $storage1->id, 'product_id' => $productA->id,
            'qty' => 5, 'type' => StockMovement::TYPE_PURCHASE, 'source_id' => 1, 'happened_at' => '2026-01-01',
        ]);
        StockMovement::create([
            'storage_id' => $storage2->id, 'product_id' => $productA->id,
            'qty' => 7, 'type' => StockMovement::TYPE_PURCHASE, 'source_id' => 2, 'happened_at' => '2026-01-01',
        ]);
        StockMovement::create([
            'storage_id' => $storage1->id, 'product_id' => $productB->id,
            'qty' => 3, 'type' => StockMovement::TYPE_PURCHASE, 'source_id' => 3, 'happened_at' => '2026-01-01',
        ]);

        $response = $this->getJson('/api/storages/remaining?date=2026-01-31')->assertOk();
        $rows = collect($response->json('data'));

        $this->assertSame(5, $rows->firstWhere(fn ($r) => $r['storage_id'] === $storage1->id && $r['product_id'] === $productA->id)['qty']);
        $this->assertSame(7, $rows->firstWhere(fn ($r) => $r['storage_id'] === $storage2->id && $r['product_id'] === $productA->id)['qty']);
        $this->assertSame(3, $rows->firstWhere(fn ($r) => $r['storage_id'] === $storage1->id && $r['product_id'] === $productB->id)['qty']);
    }

    public function test_remaining_quantities_requires_a_date(): void
    {
        $this->getJson('/api/storages/remaining')->assertStatus(422);
    }

    public function test_batch_profit_reflects_purchase_sale_and_both_kinds_of_refund(): void
    {
        [$provider, $product, $storage] = $this->makeProviderProductAndStorage();
        $client = $this->makeClient();

        $purchases = new PurchaseService();
        $orders = new OrderService();
        $refunds = new RefundService();

        $batch = $purchases->purchase($provider->id, [
            ['product_id' => $product->id, 'storage_id' => $storage->id, 'qty' => 10, 'purchase_price' => 5],
        ], '2026-01-01');
        // cost so far: 10 * 5 = 50

        $order = $orders->createOrder($client->id, [
            ['id' => $product->id, 'qty' => 6],
        ]);
        // revenue so far: 6 * price(100, default from makeProduct) -- override sale price via product price
        $orderItem = $order->items->first();
        $salePrice = (float) $orderItem->sale_price;
        // revenue so far: 6 * salePrice

        $batchItem = $batch->items->first();
        $refunds->refundSingleBatchItem(['batch_item_id' => $batchItem->id, 'qty' => 2]);
        // cost -= 2 * 5 = 10  => cost = 40

        $refunds->refundOrder([['order_item_id' => $orderItem->id, 'qty' => 1]]);
        // revenue -= 1 * salePrice

        $expectedCost = 50 - (2 * 5);
        $expectedRevenue = (6 * $salePrice) - (1 * $salePrice);
        $expectedProfit = $expectedRevenue - $expectedCost;

        $response = $this->getJson('/api/batches/profit')->assertOk();
        $row = collect($response->json('data'))->firstWhere('batch_id', $batch->id);

        $this->assertNotNull($row);
        $this->assertEqualsWithDelta($expectedCost, $row['cost'], 0.001);
        $this->assertEqualsWithDelta($expectedRevenue, $row['revenue'], 0.001);
        $this->assertEqualsWithDelta($expectedProfit, $row['profit'], 0.001);
    }

    public function test_batch_profit_is_zero_revenue_full_cost_when_nothing_has_sold_yet(): void
    {
        [$provider, $product, $storage] = $this->makeProviderProductAndStorage();
        $purchases = new PurchaseService();

        $batch = $purchases->purchase($provider->id, [
            ['product_id' => $product->id, 'storage_id' => $storage->id, 'qty' => 4, 'purchase_price' => 12.5],
        ], '2026-01-01');

        $response = $this->getJson('/api/batches/profit')->assertOk();
        $row = collect($response->json('data'))->firstWhere('batch_id', $batch->id);

        // JSON has no distinct float type, so a whole number round-trips as a PHP int
        // (50, not 50.0) — compare numerically rather than with assertSame().
        $this->assertEqualsWithDelta(50.0, $row['cost'], 0.001);
        $this->assertEqualsWithDelta(0.0, $row['revenue'], 0.001);
        $this->assertEqualsWithDelta(-50.0, $row['profit'], 0.001);
    }
}
