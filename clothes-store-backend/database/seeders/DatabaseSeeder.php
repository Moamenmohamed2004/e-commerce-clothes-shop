<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);

        $categories = [
            ['name' => 'T-Shirts', 'slug' => 't-shirts'],
            ['name' => 'Hoodies', 'slug' => 'hoodies'],
            ['name' => 'Pants', 'slug' => 'pants'],
            ['name' => 'Jackets', 'slug' => 'jackets'],
            ['name' => 'Accessories', 'slug' => 'accessories'],
        ];

        foreach ($categories as $cat) {
            \App\Models\Category::create($cat);
        }

        $products = [
            [
                'name' => 'Essential Cotton Tee',
                'slug' => 'essential-cotton-tee',
                'description' => 'A refined take on the everyday t-shirt. Cut from heavyweight organic cotton with a relaxed silhouette and subtly dropped shoulder.',
                'price' => 45,
                'category_id' => 1,
                'stock' => 50,
                'sku' => 'TS-ESS-CRM',
                'is_new_arrival' => true,
            ],
            [
                'name' => 'Midnight Hoodie',
                'slug' => 'midnight-hoodie',
                'description' => 'A premium heavy-weight hoodie for everyday comfort.',
                'price' => 145,
                'category_id' => 2,
                'stock' => 30,
                'sku' => 'HD-MID-BLK',
            ],
            [
                'name' => 'Pleated Wool Trouser',
                'slug' => 'pleated-wool-trouser',
                'description' => 'Relaxed tailored trousers cut from Italian wool blend.',
                'price' => 220,
                'category_id' => 3,
                'stock' => 15,
                'sku' => 'TR-PLT-CHR',
            ],
        ];

        foreach ($products as $prod) {
            $product = \App\Models\Product::create($prod);
            // Attach colors and sizes and images if necessary
            \App\Models\ProductImage::create(['product_id' => $product->id, 'image_url' => '/placeholder.jpg']);
        }
    }
}
