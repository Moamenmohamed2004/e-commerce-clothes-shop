<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with(['categoryModel', 'imagesModel', 'colorsModel', 'sizesModel']);

        if ($request->has('category_slug')) {
            $category = Category::where('slug', $request->category_slug)->first();
            if ($category) {
                $query->where('category_id', $category->id);
            }
        }

        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        if ($request->has('is_new_arrival')) {
            $query->where('is_new_arrival', true);
        }

        return response()->json($query->get());
    }

    public function show($slug)
    {
        $product = Product::with(['categoryModel', 'imagesModel', 'colorsModel', 'sizesModel'])
            ->where('slug', $slug)
            ->firstOrFail();
            
        return response()->json($product);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'slug' => 'required|string|unique:products',
            'description' => 'nullable|string',
            'price' => 'required|numeric',
            'discount_price' => 'nullable|numeric',
            'category_id' => 'required|exists:categories,id',
            'stock' => 'required|integer',
            'sku' => 'nullable|string|unique:products',
            'is_new_arrival' => 'boolean',
            'is_sale' => 'boolean',
            'is_sold_out' => 'boolean',
            'specifications' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120',
            'colors' => 'nullable|array',
            'sizes' => 'nullable|array',
        ]);

        $data = $request->except(['images', 'colors', 'sizes', 'retained_images']);
        if (!$request->boolean('is_sale') || empty($data['discount_price']) || (float)$data['discount_price'] >= (float)$data['price']) {
            $data['discount_price'] = null;
        }
        $product = Product::create($data);
        
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                $path = $file->store('products', 'public');
                $product->imagesModel()->create(['image_url' => $path]);
            }
        }

        if ($request->has('colors')) {
            foreach($request->colors as $c) {
                $product->colorsModel()->create(['name' => $c]);
            }
        }
        if ($request->has('sizes')) {
            foreach($request->sizes as $s) {
                $product->sizesModel()->create(['name' => $s]);
            }
        }

        return response()->json($product->load(['categoryModel', 'imagesModel', 'colorsModel', 'sizesModel']), 201);
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string',
            'slug' => 'sometimes|required|string|unique:products,slug,' . $product->id,
            'description' => 'nullable|string',
            'price' => 'sometimes|required|numeric',
            'discount_price' => 'nullable|numeric',
            'category_id' => 'sometimes|required|exists:categories,id',
            'stock' => 'sometimes|required|integer',
            'sku' => 'nullable|string|unique:products,sku,' . $product->id,
            'is_new_arrival' => 'boolean',
            'is_sale' => 'boolean',
            'is_sold_out' => 'boolean',
            'specifications' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120',
            'colors' => 'nullable|array',
            'sizes' => 'nullable|array',
            'retained_images' => 'nullable|array'
        ]);

        $data = $request->except(['images', 'colors', 'sizes', 'retained_images']);
        if (!$request->boolean('is_sale') || empty($data['discount_price']) || (float)$data['discount_price'] >= (float)($data['price'] ?? $product->price)) {
            $data['discount_price'] = null;
        }
        $product->update($data);

        // Handle retained images (delete ones that aren't retained) only if the field is sent
        if ($request->has('retained_images')) {
            $retainedIds = $request->retained_images ?? [];
            $existingImages = $product->imagesModel()->get();
            
            foreach ($existingImages as $img) {
                if (!in_array($img->id, $retainedIds)) {
                    // If it's a relative path starting with products/ or an old URL containing it
                    if (str_starts_with($img->image_url, 'products/')) {
                        \Illuminate\Support\Facades\Storage::disk('public')->delete($img->image_url);
                    } elseif (str_contains($img->image_url, 'storage/products/')) {
                        $path = str_replace(url('storage') . '/', '', $img->image_url);
                        \Illuminate\Support\Facades\Storage::disk('public')->delete($path);
                    }
                    $img->delete();
                }
            }
        }

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                $path = $file->store('products', 'public');
                $product->imagesModel()->create(['image_url' => $path]);
            }
        }

        // Handle colors (sync-like approach by deleting and recreating) — only if colors were sent
        if ($request->has('colors')) {
            $product->colorsModel()->delete();
            foreach($request->colors as $c) {
                $product->colorsModel()->create(['name' => $c]);
            }
        }

        // Handle sizes — only if sizes were sent
        if ($request->has('sizes')) {
            $product->sizesModel()->delete();
            foreach($request->sizes as $s) {
                $product->sizesModel()->create(['name' => $s]);
            }
        }

        return response()->json($product->load(['categoryModel', 'imagesModel', 'colorsModel', 'sizesModel']));
    }

    public function destroy(Product $product)
    {
        $product->delete();
        return response()->json(null, 204);
    }
}
