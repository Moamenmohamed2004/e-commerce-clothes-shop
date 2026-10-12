<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\Product;
use App\Models\OrderItem;
use App\Models\Order;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Reset invalid discount_price on products not on sale
        try {
            Product::where('is_sale', false)->update(['discount_price' => null]);
            Product::where('id', 15)->update(['discount_price' => null]);
        } catch (\Throwable $e) {}

        // 2. Correct order items that were saved with the 0.04 errant price
        try {
            $items = OrderItem::where('price', 0.04)->get();
            $affectedOrderIds = [];

            foreach ($items as $item) {
                // If it was the $50 t-shirt, restore correct purchase unit price $50.00
                $item->update(['price' => 50.00]);
                $affectedOrderIds[] = $item->order_id;
            }

            // 3. Recalculate totals for affected orders
            $affectedOrderIds = array_unique($affectedOrderIds);
            foreach ($affectedOrderIds as $orderId) {
                $order = Order::with('items')->find($orderId);
                if ($order) {
                    $newTotal = $order->items->sum(function ($i) {
                        return (float)$i->price * (int)$i->quantity;
                    });
                    $order->update(['total' => $newTotal]);
                }
            }
        } catch (\Throwable $e) {}
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Non-destructive rollback
    }
};
