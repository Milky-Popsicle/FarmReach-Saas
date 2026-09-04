<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;

class MarketplaceController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with(['farmer', 'category'])
            ->where('is_active', true);

        // Search by product name or farmer name
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%")
                  ->orWhereHas('farmer', function ($fq) use ($search) {
                      $fq->where('name', 'LIKE', "%{$search}%")
                         ->orWhere('address', 'LIKE', "%{$search}%");
                  });
            });
        }

        // Filter by category
        if ($request->filled('category')) {
            $query->where('category_id', $request->category);
        }

        // Filter by specific farmer
        if ($request->filled('farmer_id')) {
            $query->where('user_id', $request->farmer_id);
        }

        // Price sorting
        if ($request->get('sort') === 'price_asc') {
            $query->orderBy('price', 'asc');
        } elseif ($request->get('sort') === 'price_desc') {
            $query->orderBy('price', 'desc');
        } else {
            $query->latest();
        }

        $products = $query->paginate(12)->withQueryString();
        $categories = Category::withCount('products')->get();
        $farmers = User::where('role', 'farmer')->withCount('products')->get();

        // Featured products for carousel
        $featuredProducts = Product::with(['farmer', 'category'])
            ->where('is_active', true)
            ->where('quantity', '>', 0)
            ->inRandomOrder()
            ->take(6)
            ->get();

        // Total counts for header stats
        $totalProductsCount = Product::where('is_active', true)->count();
        $totalFarmersCount = $farmers->count();

        return view('marketplace.index', compact(
            'products',
            'categories',
            'farmers',
            'featuredProducts',
            'totalProductsCount',
            'totalFarmersCount'
        ));
    }

    public function show(Product $product)
    {
        $product->load(['farmer', 'category']);

        // Other offerings from the same farmer
        $moreFromFarmer = Product::where('user_id', $product->user_id)
            ->where('id', '!=', $product->id)
            ->where('is_active', true)
            ->take(4)
            ->get();

        // Similar products from other farmers for real-time price comparison
        $priceComparisons = Product::with('farmer')
            ->where('id', '!=', $product->id)
            ->where('category_id', $product->category_id)
            ->where('is_active', true)
            ->take(4)
            ->get();

        return view('marketplace.show', compact('product', 'moreFromFarmer', 'priceComparisons'));
    }

    public function comparePrices(Request $request)
    {
        $categories = Category::all();
        $selectedCategory = $request->get('category_id', $categories->first()?->id);

        $comparisonProducts = Product::with(['farmer', 'category'])
            ->where('is_active', true)
            ->when($selectedCategory, fn($q) => $q->where('category_id', $selectedCategory))
            ->orderBy('price', 'asc')
            ->get();

        return view('marketplace.compare', compact('categories', 'selectedCategory', 'comparisonProducts'));
    }
}
