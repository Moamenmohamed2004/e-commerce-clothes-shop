<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Customer;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\RefreshDatabase;

class OrderDetailsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_single_product_order_retrieval()
    {
        $admin = User::first() ?? User::factory()->create();
        Sanctum::actingAs($admin);

        $category = Category::firstOrCreate(['slug' => 'test-cat'], ['name' => 'Test Cat']);
        $product = Product::firstOrCreate(
            ['slug' => 'test-jacket'],
            [
                'name' => 'Vintage Leather Jacket',
                'description' => 'Test description',
                'price' => 120.00,
                'category_id' => $category->id,
                'stock' => 10,
                'sku' => 'JKT-001',
            ]
        );

        $order = Order::create([
            'order_number' => 'ORD-TEST-001',
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
            'customer_phone' => '+1234567890',
            'address' => '123 Main St',
            'city' => 'Metropolis',
            'total' => 120.00,
            'status' => 'Pending',
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'quantity' => 1,
            'selected_color' => 'Brown',
            'selected_size' => 'L',
            'price' => 120.00,
        ]);

        $response = $this->getJson("/api/v1/orders/{$order->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('order_number', 'ORD-TEST-001');
        $response->assertJsonPath('customer_name', 'John Doe');
        $this->assertEquals(120, $response->json('subtotal'));
        $this->assertEquals(120, $response->json('total'));
        $response->assertJsonPath('items.0.display_name', 'Vintage Leather Jacket');
        $response->assertJsonPath('items.0.selected_color', 'Brown');
        $response->assertJsonPath('items.0.selected_size', 'L');
        $this->assertEquals(1, $response->json('items.0.quantity'));
        $this->assertEquals(120, $response->json('items.0.price'));
        $this->assertEquals(120, $response->json('items.0.subtotal'));

        $order->delete();
    }

    public function test_multiple_product_order_retrieval()
    {
        $admin = User::first() ?? User::factory()->create();
        Sanctum::actingAs($admin);

        $category = Category::firstOrCreate(['slug' => 'test-cat'], ['name' => 'Test Cat']);
        $product1 = Product::firstOrCreate(['slug' => 'test-shirt-1'], [
            'name' => 'Shirt 1',
            'description' => 'Test',
            'price' => 50.00,
            'category_id' => $category->id,
            'stock' => 10,
        ]);
        $product2 = Product::firstOrCreate(['slug' => 'test-pants-1'], [
            'name' => 'Pants 1',
            'description' => 'Test',
            'price' => 80.00,
            'category_id' => $category->id,
            'stock' => 10,
        ]);

        $order = Order::create([
            'order_number' => 'ORD-TEST-002',
            'customer_name' => 'Jane Smith',
            'customer_email' => 'jane@example.com',
            'customer_phone' => '+1987654321',
            'address' => '456 Park Ave',
            'city' => 'Gotham',
            'total' => 210.00,
            'status' => 'Pending',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product1->id,
            'product_name' => 'Shirt 1',
            'quantity' => 1,
            'selected_color' => 'White',
            'selected_size' => 'M',
            'price' => 50.00,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product2->id,
            'product_name' => 'Pants 1',
            'quantity' => 2,
            'selected_color' => 'Black',
            'selected_size' => '32',
            'price' => 80.00,
        ]);

        $response = $this->getJson("/api/v1/orders/{$order->id}");

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('items'));
        $response->assertJsonPath('items.0.display_name', 'Shirt 1');
        $this->assertEquals(50, $response->json('items.0.subtotal'));
        $response->assertJsonPath('items.1.display_name', 'Pants 1');
        $this->assertEquals(160, $response->json('items.1.subtotal'));
        $this->assertEquals(210, $response->json('subtotal'));
        $this->assertEquals(210, $response->json('total'));

        $order->delete();
    }

    public function test_historical_preservation_when_product_catalog_changes()
    {
        $admin = User::first() ?? User::factory()->create();
        Sanctum::actingAs($admin);

        $category = Category::firstOrCreate(['slug' => 'test-cat'], ['name' => 'Test Cat']);
        $product = Product::create([
            'name' => 'Original Cotton Hoodie',
            'slug' => 'original-cotton-hoodie-' . uniqid(),
            'description' => 'Test',
            'price' => 60.00,
            'category_id' => $category->id,
            'stock' => 10,
        ]);

        $order = Order::create([
            'order_number' => 'ORD-HIST-' . uniqid(),
            'customer_name' => 'Alice Walker',
            'customer_phone' => '123456789',
            'address' => '789 Oak St',
            'city' => 'Star City',
            'total' => 60.00,
            'status' => 'Pending',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'price' => 60.00,
            'selected_color' => 'Navy',
            'selected_size' => 'XL',
        ]);

        // Now modify the catalog product's name and price
        $product->update([
            'name' => 'Redesigned Premium Hoodie',
            'price' => 120.00,
        ]);

        $response = $this->getJson("/api/v1/orders/{$order->id}");

        $response->assertStatus(200);
        // The order item MUST retain the purchase-time price ($60) and snapshot name
        $this->assertEquals(60, $response->json('items.0.price'));
        $this->assertEquals(60, $response->json('items.0.subtotal'));
        $response->assertJsonPath('items.0.product_name', 'Original Cotton Hoodie');
        $response->assertJsonPath('items.0.display_name', 'Original Cotton Hoodie');

        $order->delete();
        $product->delete();
    }

    public function test_missing_or_deleted_product_fallback()
    {
        $admin = User::first() ?? User::factory()->create();
        Sanctum::actingAs($admin);

        $order = Order::create([
            'order_number' => 'ORD-DEL-' . uniqid(),
            'customer_name' => 'Bob Davis',
            'customer_phone' => '123456789',
            'address' => '321 Elm St',
            'city' => 'Central City',
            'total' => 75.00,
            'status' => 'Pending',
        ]);

        // Item with null product_id and no snapshot (e.g. legacy deleted item)
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => null,
            'product_name' => null,
            'product_image' => null,
            'quantity' => 1,
            'price' => 75.00,
            'selected_color' => 'Grey',
            'selected_size' => 'M',
        ]);

        $response = $this->getJson("/api/v1/orders/{$order->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('items.0.display_name', 'Product Unavailable (Archived)');
        $this->assertEquals(75, $response->json('items.0.price'));
        $this->assertEquals(75, $response->json('items.0.subtotal'));

        $order->delete();
    }

    public function test_order_status_update_persists()
    {
        $admin = User::first() ?? User::factory()->create();
        Sanctum::actingAs($admin);

        $order = Order::create([
            'order_number' => 'ORD-STATUS-' . uniqid(),
            'customer_name' => 'Status Tester',
            'customer_phone' => '123456789',
            'address' => 'Status Road',
            'city' => 'Coast City',
            'total' => 50.00,
            'status' => 'Pending',
        ]);

        $response = $this->putJson("/api/v1/orders/{$order->id}", [
            'status' => 'Shipped',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'Shipped');

        $this->assertEquals('Shipped', $order->fresh()->status);

        $order->delete();
    }

    public function test_order_route_binding_by_order_number()
    {
        $admin = User::first() ?? User::factory()->create();
        Sanctum::actingAs($admin);

        $orderNum = 'ORD-NUM-' . strtoupper(uniqid());
        $order = Order::create([
            'order_number' => $orderNum,
            'customer_name' => 'Lookup Tester',
            'customer_phone' => '123456789',
            'address' => 'Lookup Road',
            'city' => 'Keystone City',
            'total' => 50.00,
            'status' => 'Confirmed',
        ]);

        // Lookup by public order_number string
        $response = $this->getJson("/api/v1/orders/{$orderNum}");
        $response->assertStatus(200);
        $response->assertJsonPath('id', $order->id);
        $response->assertJsonPath('order_number', $orderNum);

        $order->delete();
    }

    public function test_checkout_creates_snapshots_and_consistent_order_number()
    {
        $product = Product::first();
        if (!$product) {
            $category = Category::firstOrCreate(['slug' => 'test-cat'], ['name' => 'Test Cat']);
            $product = Product::create([
                'name' => 'Checkout Product',
                'slug' => 'checkout-prod-' . uniqid(),
                'description' => 'Test',
                'price' => 45.00,
                'category_id' => $category->id,
                'stock' => 10,
            ]);
        }

        $payload = [
            'customer_name' => 'Checkout User',
            'customer_email' => 'user@checkout.com',
            'customer_phone' => '+201112223344',
            'city' => 'Alexandria',
            'address' => 'Corniche Road',
            'notes' => 'Ring the bell',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'selected_color' => 'Blue',
                    'selected_size' => 'M',
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/checkout', $payload);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $orderId = $response->json('orderId');
        $this->assertNotEmpty($orderId);

        // Verify the database record has the exact same order number
        $order = Order::where('order_number', $orderId)->first();
        $this->assertNotNull($order);
        $this->assertEquals($orderId, $order->order_number);

        // Verify snapshot was recorded on the order item
        $firstItem = $order->items()->first();
        $this->assertEquals($product->name, $firstItem->product_name);
        $this->assertEquals('Blue', $firstItem->selected_color);
        $this->assertEquals('M', $firstItem->selected_size);

        // Clean up
        $order->delete();
    }

    public function test_pricing_calculation_and_subtotals()
    {
        $category = Category::firstOrCreate(['slug' => 'test-cat'], ['name' => 'Test Cat']);
        
        // Product with regular price $50, but an errant discount_price in DB while is_sale is false
        $product = Product::create([
            'name' => 'Classic Cotton T-Shirt',
            'slug' => 'classic-cotton-tee-' . uniqid(),
            'description' => 'Test',
            'price' => 50.00,
            'discount_price' => 0.04,
            'is_sale' => false,
            'category_id' => $category->id,
            'stock' => 50,
        ]);

        // Test 1: Unit price: $50, quantity: 3 -> subtotal: $150
        $res3 = $this->postJson('/api/v1/checkout', [
            'customer_name' => 'Customer 3',
            'customer_phone' => '111222333',
            'city' => 'Cairo',
            'address' => 'Street 1',
            'items' => [['product_id' => $product->id, 'quantity' => 3]],
        ]);
        $res3->assertStatus(201);
        $order3 = Order::where('order_number', $res3->json('orderId'))->first();
        $this->assertEquals(50.00, $order3->items[0]->price);
        $this->assertEquals(150.00, $order3->items[0]->subtotal);
        $this->assertEquals(150.00, $order3->subtotal);
        $this->assertEquals(150.00, $order3->total);

        // Test 2: Unit price: $50, quantity: 1 -> subtotal: $50
        $res1 = $this->postJson('/api/v1/checkout', [
            'customer_name' => 'Customer 1',
            'customer_phone' => '111222333',
            'city' => 'Cairo',
            'address' => 'Street 1',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);
        $res1->assertStatus(201);
        $order1 = Order::where('order_number', $res1->json('orderId'))->first();
        $this->assertEquals(50.00, $order1->items[0]->price);
        $this->assertEquals(50.00, $order1->items[0]->subtotal);
        $this->assertEquals(50.00, $order1->subtotal);
        $this->assertEquals(50.00, $order1->total);

        // Test 3: Unit price: $50, quantity: 2 -> subtotal: $100
        $res2 = $this->postJson('/api/v1/checkout', [
            'customer_name' => 'Customer 2',
            'customer_phone' => '111222333',
            'city' => 'Cairo',
            'address' => 'Street 1',
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ]);
        $res2->assertStatus(201);
        $order2 = Order::where('order_number', $res2->json('orderId'))->first();
        $this->assertEquals(50.00, $order2->items[0]->price);
        $this->assertEquals(100.00, $order2->items[0]->subtotal);
        $this->assertEquals(100.00, $order2->subtotal);
        $this->assertEquals(100.00, $order2->total);

        // Test 4: Product legitimately on sale with valid discount price $35 (price: $50)
        $saleProduct = Product::create([
            'name' => 'Sale Polo Shirt',
            'slug' => 'sale-polo-' . uniqid(),
            'description' => 'Test',
            'price' => 50.00,
            'discount_price' => 35.00,
            'is_sale' => true,
            'category_id' => $category->id,
            'stock' => 50,
        ]);
        $resSale = $this->postJson('/api/v1/checkout', [
            'customer_name' => 'Sale Customer',
            'customer_phone' => '111222333',
            'city' => 'Cairo',
            'address' => 'Street 1',
            'items' => [['product_id' => $saleProduct->id, 'quantity' => 2]],
        ]);
        $resSale->assertStatus(201);
        $orderSale = Order::where('order_number', $resSale->json('orderId'))->first();
        $this->assertEquals(35.00, $orderSale->items[0]->price);
        $this->assertEquals(70.00, $orderSale->items[0]->subtotal);
        $this->assertEquals(70.00, $orderSale->subtotal);
        $this->assertEquals(70.00, $orderSale->total);
    }
}
