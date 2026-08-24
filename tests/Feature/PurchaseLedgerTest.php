<?php

namespace Tests\Feature;

use App\Models\StockMovement;
use App\Services\PurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\InteractsWithInventory;
use Tests\TestCase;

class PurchaseLedgerTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithInventory;

    private PurchaseService $purchases;

    protected function setUp(): void
    {
        parent::setUp();
        $this->purchases = new PurchaseService();
    }

    public function test_purchase_writes_one_purchase_movement_per_line(): void
    {
        [$provider, $productA, $storage] = $this->makeProviderProductAndStorage();
        $productB = $this->makeProduct($productA->category);

        $batch = $this->purchases->purchase($provider->id, [
            ['product_id' => $productA->id, 'storage_id' => $storage->id, 'qty' => 10, 'purchase_price' => 5],
            ['product_id' => $productB->id, 'storage_id' => $storage->id, 'qty' => 4, 'purchase_price' => 8],
        ], '2026-01-10');

        $this->assertSame(2, StockMovement::count());

        $movementA = StockMovement::where('product_id', $productA->id)->sole();
        $this->assertSame(StockMovement::TYPE_PURCHASE, $movementA->type);
        $this->assertSame(10, $movementA->qty);
        $this->assertSame($storage->id, $movementA->storage_id);
        $this->assertSame('2026-01-10', $movementA->happened_at->toDateString());

        $batchItemA = $batch->items->firstWhere('product_id', $productA->id);
        $this->assertSame($batchItemA->id, $movementA->source_id);

        $movementB = StockMovement::where('product_id', $productB->id)->sole();
        $this->assertSame(4, $movementB->qty);
    }

    public function test_purchase_defaults_happened_at_to_today_when_not_given(): void
    {
        [$provider, $product, $storage] = $this->makeProviderProductAndStorage();

        $this->purchases->purchase($provider->id, [
            ['product_id' => $product->id, 'storage_id' => $storage->id, 'qty' => 1, 'purchase_price' => 1],
        ]);

        $movement = StockMovement::sole();
        $this->assertSame(now()->toDateString(), $movement->happened_at->toDateString());
    }

    public function test_purchase_rejects_a_product_that_does_not_belong_to_the_provider(): void
    {
        [$provider, $product, $storage] = $this->makeProviderProductAndStorage();
        $otherProvider = $this->makeProvider();

        $this->expectException(\DomainException::class);

        try {
            $this->purchases->purchase($otherProvider->id, [
                ['product_id' => $product->id, 'storage_id' => $storage->id, 'qty' => 1, 'purchase_price' => 1],
            ]);
        } finally {
            $this->assertSame(0, StockMovement::count());
        }
    }

    public function test_empty_lines_are_rejected_before_any_batch_is_created(): void
    {
        $provider = $this->makeProvider();

        $this->expectException(\InvalidArgumentException::class);
        $this->purchases->purchase($provider->id, []);
    }
}
