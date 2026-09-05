<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FarmerProductController extends Controller
{
    public function marketplace(Request $request)
    {
        $farmer = Auth::user();

        $query = Product::with(['farmer', 'category'])
            ->where('is_active', true)
            ->whereHas('farmer');

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where('name', 'LIKE', "%{$search}%");
        }

        $allProducts = $query->paginate(12);

        $farmerOrdersCount = Order::where('farmer_id', $farmer->id)->count();
        $farmerPendingOrders = Order::where('farmer_id', $farmer->id)->where('status', 'pending')->count();
        $farmerTotalSales = Order::where('farmer_id', $farmer->id)->where('status', 'delivered')->sum('total_amount');

        $featuredProducts = Product::with('farmer')
            ->where('is_active', true)
            ->whereHas('farmer')
            ->inRandomOrder()
            ->take(6)
            ->get();

        return view('farmer.marketplace', compact(
            'allProducts',
            'farmerOrdersCount',
            'farmerPendingOrders',
            'farmerTotalSales',
            'featuredProducts'
        ));
    }

    public function index()
    {
        $farmer = Auth::user();

        $myProducts = Product::with('category')
            ->where('user_id', $farmer->id)
            ->latest()
            ->get();

        $incomingOrders = Order::with(['buyer', 'items.product', 'payment'])
            ->where('farmer_id', $farmer->id)
            ->latest()
            ->take(10)
            ->get();

        $categories = Category::all();

        return view('farmer.products', compact('myProducts', 'incomingOrders', 'categories', 'farmer'));
    }

    public function store(Request $request)
    {
        $farmer = Auth::user();

        $validated = $request->validate([
            'name' => 'required|string|max:190',
            'category_id' => 'required|exists:categories,id',
            'price' => 'required|numeric|min:1',
            'quantity' => 'required|integer|min:0',
            'kilograms' => 'required|numeric|min:0.1',
            'unit' => 'nullable|string|max:20',
            'description' => 'nullable|string|max:1000',
            'image' => 'nullable|image|max:2048',
        ]);

        $imagePath = 'https://images.unsplash.com/photo-1598170845058-32b9d6a5da37?w=600&auto=format&fit=crop&q=80';
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('products', 'public');
            $imagePath = '/storage/'.$path;
        }

        $product = Product::create([
            'user_id' => $farmer->id,
            'category_id' => $validated['category_id'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'price' => $validated['price'],
            'quantity' => $validated['quantity'],
            'kilograms' => $validated['kilograms'],
            'unit' => $validated['unit'] ?? 'kg',
            'image_path' => $imagePath,
            'is_active' => true,
        ]);

        ActivityLog::log($farmer->id, 'Product Created', "Farmer {$farmer->name} listed new product: {$product->name}.");

        return back()->with('success', "Product '{$product->name}' has been added successfully!");
    }

    public function update(Request $request, Product $product)
    {
        $farmer = Auth::user();
        if ($product->user_id !== $farmer->id) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:190',
            'category_id' => 'required|exists:categories,id',
            'price' => 'required|numeric|min:1',
            'quantity' => 'required|integer|min:0',
            'kilograms' => 'required|numeric|min:0.1',
            'unit' => 'nullable|string|max:20',
            'description' => 'nullable|string|max:1000',
            'image' => 'nullable|image|max:2048',
        ]);

        $data = [
            'name' => $validated['name'],
            'category_id' => $validated['category_id'],
            'price' => $validated['price'],
            'quantity' => $validated['quantity'],
            'kilograms' => $validated['kilograms'],
            'unit' => $validated['unit'] ?? $product->unit,
            'description' => $validated['description'] ?? $product->description,
        ];

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('products', 'public');
            $data['image_path'] = '/storage/'.$path;
        }

        $product->update($data);

        ActivityLog::log($farmer->id, 'Product Updated', "Farmer {$farmer->name} updated product: {$product->name}.");

        return back()->with('success', "Product '{$product->name}' updated successfully!");
    }

    public function destroy(Product $product)
    {
        $farmer = Auth::user();
        if ($product->user_id !== $farmer->id) {
            abort(403);
        }

        $name = $product->name;
        $product->delete();

        ActivityLog::log($farmer->id, 'Product Deleted', "Farmer {$farmer->name} deleted product: {$name}.");

        return back()->with('success', "Product '{$name}' has been deleted.");
    }

    public function toggleStock(Product $product)
    {
        $farmer = Auth::user();
        if ($product->user_id !== $farmer->id) {
            abort(403);
        }

        $product->update(['is_active' => ! $product->is_active]);

        return back()->with('success', 'Product status updated.');
    }
}
