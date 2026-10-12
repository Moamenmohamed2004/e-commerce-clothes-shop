<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index()
    {
        $orders = Order::with(['items.product.imagesModel', 'items.product.categoryModel', 'customer'])
            ->latest()
            ->get();

        return response()->json($orders);
    }

    public function show(Order $order)
    {
        $order->load(['items.product.imagesModel', 'items.product.categoryModel', 'customer']);
        return response()->json($order);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_name' => 'required|string',
            'customer_email' => 'nullable|email',
            'customer_phone' => 'required|string',
            'address' => 'required|string',
            'city' => 'required|string',
            'notes' => 'nullable|string',
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.selected_color' => 'nullable|string',
            'items.*.selected_size' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            $total = 0;
            foreach ($validated['items'] as $item) {
                $product = Product::findOrFail($item['product_id']);
                if ($product->stock < $item['quantity']) {
                    throw new \Exception("Not enough stock for {$product->name}");
                }
                $price = (float) $product->effective_price;
                $total += $price * $item['quantity'];
                
                // Deduct stock
                $product->decrement('stock', $item['quantity']);
                if ($product->stock === 0) {
                    $product->update(['is_sold_out' => true]);
                }
            }

            // Create or update customer
            $customer = Customer::updateOrCreate(
                ['phone' => $validated['customer_phone']],
                [
                    'name' => $validated['customer_name'],
                    'email' => $validated['customer_email'] ?? null,
                ]
            );
            
            $customer->increment('total_orders');
            $customer->update(['last_order_date' => now()]);

            $order = Order::create([
                'order_number' => 'ORD-' . strtoupper(uniqid()),
                'customer_id' => $customer->id,
                'customer_name' => $validated['customer_name'],
                'customer_email' => $validated['customer_email'] ?? null,
                'customer_phone' => $validated['customer_phone'],
                'address' => $validated['address'],
                'city' => $validated['city'],
                'notes' => $validated['notes'] ?? '',
                'total' => $total,
                'status' => 'Pending'
            ]);

            foreach ($validated['items'] as $item) {
                $product = Product::findOrFail($item['product_id']);
                $price = (float) $product->effective_price;
                $firstImage = !empty($product->images) ? $product->images[0] : null;
                
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_image' => $firstImage,
                    'product_sku' => $product->sku ?? null,
                    'quantity' => $item['quantity'],
                    'selected_color' => $item['selected_color'] ?? null,
                    'selected_size' => $item['selected_size'] ?? null,
                    'price' => $price,
                ]);
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'orderId' => $order->order_number,
                'order' => $order->load(['items.product.imagesModel', 'items.product.categoryModel', 'customer'])
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function update(Request $request, Order $order)
    {
        $validated = $request->validate([
            'status' => 'required|string|in:Pending,Confirmed,Preparing,Shipped,Delivered,Cancelled,Returned,Refunded'
        ]);

        $order->update($validated);
        $order->load(['items.product.imagesModel', 'items.product.categoryModel', 'customer']);
        return response()->json($order);
    }

    public function destroy(Order $order)
    {
        $order->delete();
        return response()->json(['success' => true, 'message' => 'Order deleted successfully']);
    }
}
