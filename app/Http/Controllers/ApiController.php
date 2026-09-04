<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApiController extends Controller
{
    /**
     * JSON API for Products
     */
    public function products(Request $request): JsonResponse
    {
        $query = Product::with(['farmer:id,name,address,phone_number', 'category:id,name,slug'])
            ->where('is_active', true);

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('farmer_id')) {
            $query->where('user_id', $request->farmer_id);
        }

        if ($request->filled('search')) {
            $query->where('name', 'LIKE', '%' . $request->search . '%');
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->get(),
        ]);
    }

    /**
     * JSON API for Single Product Details
     */
    public function productDetails(Product $product): JsonResponse
    {
        $product->load(['farmer:id,name,address,phone_number,bio', 'category:id,name,slug']);
        return response()->json([
            'status' => 'success',
            'data' => $product,
        ]);
    }

    /**
     * JSON API for Orders
     */
    public function orders(Request $request): JsonResponse
    {
        $query = Order::with(['buyer:id,name,phone_number', 'farmer:id,name,phone_number', 'items.product', 'payment']);

        if ($request->filled('buyer_id')) {
            $query->where('buyer_id', $request->buyer_id);
        }

        if ($request->filled('farmer_id')) {
            $query->where('farmer_id', $request->farmer_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->latest()->get(),
        ]);
    }

    /**
     * JSON API for Profiles
     */
    public function profile(User $user): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone_number' => $user->phone_number,
                'role' => $user->role,
                'gender' => $user->gender,
                'birthday' => $user->birthday?->format('Y-m-d'),
                'address' => $user->address,
                'bio' => $user->bio,
                'avatar_path' => $user->avatar_path,
            ],
        ]);
    }

    /**
     * JSON API for Sales Data (30-day overview)
     */
    public function salesData(): JsonResponse
    {
        $sales = Order::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(total_amount) as total_sales'),
                DB::raw('COUNT(*) as order_count')
            )
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $sales,
        ]);
    }

    /**
     * JSON API for Top Products
     */
    public function topProducts(): JsonResponse
    {
        $top = OrderItem::select(
                'product_id',
                DB::raw('SUM(quantity) as total_quantity_sold'),
                DB::raw('SUM(subtotal) as total_revenue')
            )
            ->with('product:id,name,price,image_path,user_id', 'product.farmer:id,name,address')
            ->groupBy('product_id')
            ->orderByDesc('total_quantity_sold')
            ->take(10)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $top,
        ]);
    }

    /**
     * JSON API for Recent Activities
     */
    public function recentActivities(): JsonResponse
    {
        $logs = ActivityLog::with('user:id,name,role')->latest()->take(25)->get();
        return response()->json([
            'status' => 'success',
            'data' => $logs,
        ]);
    }

    /**
     * JSON API for Recipes and Ingredients
     */
    public function recipes(): JsonResponse
    {
        $recipes = Recipe::with('ingredients.category')->latest()->get();
        return response()->json([
            'status' => 'success',
            'data' => $recipes,
        ]);
    }

    /**
     * JSON API for Recipe-to-Product Matching
     */
    public function recipeMatch(Recipe $recipe): JsonResponse
    {
        $recipe->load('ingredients.category');

        $ingredientMatches = [];

        foreach ($recipe->ingredients as $ingredient) {
            $matchedProducts = $ingredient->getAvailableProducts();

            $ingredientMatches[] = [
                'ingredient_id' => $ingredient->id,
                'ingredient_name' => $ingredient->name,
                'quantity_description' => $ingredient->quantity_description,
                'is_available' => $matchedProducts->isNotEmpty(),
                'matched_products' => $matchedProducts->map(fn($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'price' => (float) $p->price,
                    'kilograms' => (float) $p->kilograms,
                    'unit' => $p->unit,
                    'quantity_in_stock' => $p->quantity,
                    'farmer_name' => $p->farmer->name,
                    'farmer_address' => $p->farmer->address,
                    'farmer_id' => $p->user_id,
                ]),
            ];
        }

        return response()->json([
            'status' => 'success',
            'recipe' => [
                'id' => $recipe->id,
                'title' => $recipe->title,
                'prep_time' => $recipe->prep_time,
                'description' => $recipe->description,
            ],
            'ingredients' => $ingredientMatches,
        ]);
    }

    /**
     * JSON API for Bulk Orders
     */
    public function bulkOrders(): JsonResponse
    {
        $bulk = Order::with(['buyer:id,name,phone_number,address', 'farmer:id,name,address', 'items.product', 'payment'])
            ->where('order_type', 'bulk')
            ->latest()
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $bulk,
        ]);
    }

    /**
     * JSON API for Farmer/Seller Location Information
     */
    public function farmerLocations(): JsonResponse
    {
        $farmers = User::where('role', 'farmer')
            ->select('id', 'name', 'address', 'phone_number', 'bio')
            ->withCount('products')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $farmers,
        ]);
    }
}
