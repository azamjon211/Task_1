<?php

namespace Tests\Feature\Concerns;

use App\Models\Batch;
use App\Models\BatchItem;
use App\Models\Category;
use App\Models\Client;
use App\Models\Product;
use App\Models\Provider;
use App\Models\Storage;

trait InteractsWithInventory
{
    protected function makeProvider(array $attrs = []): Provider
    {
        return Provider::create(array_merge([
            'name' => 'Provider '.uniqid(),
        ], $attrs));
    }

    protected function makeCategory(?Provider $provider = null, array $attrs = []): Category
    {
        return Category::create(array_merge([
            'provider_id' => ($provider ?? $this->makeProvider())->id,
            'name' => 'Category '.uniqid(),
        ], $attrs));
    }

    protected function makeProduct(?Category $category = null, array $attrs = []): Product
    {
        return Product::create(array_merge([
            'category_id' => ($category ?? $this->makeCategory())->id,
            'name' => 'Product '.uniqid(),
            'price' => 100,
        ], $attrs));
    }

    protected function makeStorage(array $attrs = []): Storage
    {
        return Storage::create(array_merge([
            'name' => 'Storage '.uniqid(),
        ], $attrs));
    }

    protected function makeClient(array $attrs = []): Client
    {
        return Client::create(array_merge([
            'name' => 'Client '.uniqid(),
        ], $attrs));
    }

    protected function makeBatch(?Provider $provider = null, array $attrs = []): Batch
    {
        return Batch::create(array_merge([
            'provider_id' => ($provider ?? $this->makeProvider())->id,
            'purchased_at' => now()->toDateString(),
        ], $attrs));
    }

    protected function makeBatchItem(Batch $batch, Product $product, Storage $storage, array $attrs = []): BatchItem
    {
        $qty = $attrs['qty'] ?? 10;

        return BatchItem::create(array_merge([
            'batch_id' => $batch->id,
            'product_id' => $product->id,
            'storage_id' => $storage->id,
            'qty' => $qty,
            'purchase_price' => 10,
            'remaining_qty' => $qty,
        ], $attrs));
    }

    /**
     * Creates a product together with a provider/category that PurchaseService's
     * assertProductBelongsToProvider() check will accept, plus a storage to purchase into.
     *
     * @return array{0: Provider, 1: Product, 2: Storage}
     */
    protected function makeProviderProductAndStorage(): array
    {
        $provider = $this->makeProvider();
        $category = $this->makeCategory($provider);
        $product = $this->makeProduct($category);
        $storage = $this->makeStorage();

        return [$provider, $product, $storage];
    }
}
