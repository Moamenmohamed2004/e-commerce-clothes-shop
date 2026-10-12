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
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('product_name')->nullable()->after('product_id');
            $table->string('product_image')->nullable()->after('product_name');
            $table->string('product_sku')->nullable()->after('product_image');
        });

        // Backfill existing order items where products exist
        try {
            $items = \App\Models\OrderItem::whereNotNull('product_id')->get();
            foreach ($items as $item) {
                $product = \App\Models\Product::find($item->product_id);
                if ($product) {
                    $firstImage = !empty($product->images) ? $product->images[0] : null;
                    $item->update([
                        'product_name' => $product->name,
                        'product_image' => $firstImage,
                        'product_sku' => $product->sku,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            // Silently allow migration to complete even if model backfill encounters edge-case
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['product_name', 'product_image', 'product_sku']);
        });
    }
};
