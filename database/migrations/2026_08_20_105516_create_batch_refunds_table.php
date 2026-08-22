<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('batch_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_item_id')->constrained('batch_items')->cascadeOnDelete();
            $table->unsignedInteger('qty');
            $table->decimal('amount', 12, 2);
            $table->string('reason')->nullable();
            $table->timestamp('refunded_at');

            $table->timestamps();
            $table->index(['batch_item_id', 'refunded_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('batches_refunds');
    }
};
