<?php

namespace App\Http\Controllers;

use App\Http\Resources\OrderResource;
use App\Http\Resources\ProductResource;
use App\Http\Resources\RecipeResource;
use App\Http\Resources\UserProfileResource;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
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
        $query = Product::with(['farmer:id,name,bio', 'category:id,name,slug'])
            ->where('is_active', true)
            ->where('quantity', '>', 0);

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('farmer_id')) {
            $query->where('user_id', $request->farmer_id);
        }

        if ($request->filled('search')) {
            $query->where('name', 'LIKE', '%'.$request->search.'%');
        }

        return response()->json([
            'status' => 'success',
            'data' => ProductResource::collection($query->get()),
        ]);
    }

    /**
     * JSON API for Single Product Details
     */
    public function productDetails(Product $product): JsonResponse
    {
        if (! $product->is_active || $product->quantity <= 0) {
            abort(404);
        }

        $product->load(['farmer:id,name,bio', 'category:id,name,slug']);

        return response()->json([
            'status' => 'success',
            'data' => new ProductResource($product),
        ]);
    }

    /**
     * JSON API for Orders, scoped to the authenticated viewer.
     */
    public function orders(Request $request): JsonResponse
    {
        $viewer = $request->user();

        $query = Order::with([
            'buyer:id,name,phone_number,address',
            'farmer:id,name,phone_number,address',
            'items.product:id,name,price,unit,image_path',
            'payment',
        ]);

        $this->scopeOrdersToViewer($query, $viewer);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($viewer->isAdmin()) {
            if ($request->filled('buyer_id')) {
                $query->where('buyer_id', $request->integer('buyer_id'));
            }

            if ($request->filled('farmer_id')) {
                $query->where('farmer_id', $request->integer('farmer_id'));
            }
        }

        return response()->json([
            'status' => 'success',
            'data' => OrderResource::collection($query->latest()->get()),
        ]);
    }

    /**
     * JSON API for Profiles
     */
    public function profile(Request $request, User $user): JsonResponse
    {
        $includePrivateFields = $request->user()->can('viewPrivate', $user);

        return response()->json([
            'status' => 'success',
            'data' => new UserProfileResource($user, $includePrivateFields),
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
            ->get()
            ->map(fn ($row): array => [
                'date' => $row->date,
                'total_sales' => (float) $row->total_sales,
                'order_count' => (int) $row->order_count,
            ]);

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
            ->get()
            ->map(fn ($row): array => [
                'product_id' => $row->product_id,
                'total_quantity_sold' => (int) $row->total_quantity_sold,
                'total_revenue' => (float) $row->total_revenue,
                'product' => $row->product === null ? null : [
                    'id' => $row->product->id,
                    'name' => $row->product->name,
                    'price' => (float) $row->product->price,
                    'image_path' => $row->product->image_path,
                    'farmer' => $row->product->farmer === null ? null : [
                        'id' => $row->product->farmer->id,
                        'name' => $row->product->farmer->name,
                        'address' => $row->product->farmer->address,
                    ],
                ],
            ]);

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
        $logs = ActivityLog::with('user:id,name,role')
            ->latest()
            ->take(25)
            ->get()
            ->map(fn ($log): array => [
                'id' => $log->id,
                'action' => $log->action,
                'description' => $log->description,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at?->toIso8601String(),
                'user' => $log->user === null ? null : [
                    'id' => $log->user->id,
                    'name' => $log->user->name,
                    'role' => $log->user->role,
                ],
            ]);

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
            'data' => RecipeResource::collection($recipes),
        ]);
    }

    /**
     * JSON API for Recipe-to-Product Matching
     */
    public function recipeMatch(Request $request, Recipe $recipe): JsonResponse
    {
        $validated = $request->validate([
            'ingredient_id' => ['nullable', 'integer'],
        ]);

        $recipe->load('ingredients.category');

        $ingredients = $recipe->ingredients;

        if (($validated['ingredient_id'] ?? null) !== null) {
            $selectedIngredient = $recipe->ingredients->firstWhere('id', (int) $validated['ingredient_id']);

            if ($selectedIngredient === null) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'The selected ingredient does not belong to this recipe.',
                ], 422);
            }

            $ingredients = collect([$selectedIngredient]);
        }

        $ingredientMatches = [];

        foreach ($ingredients as $ingredient) {
            $matchedProducts = $ingredient->getAvailableProducts();

            $ingredientMatches[] = [
                'ingredient_id' => $ingredient->id,
                'ingredient_name' => $ingredient->name,
                'quantity_description' => $ingredient->quantity_description,
                'is_available' => $matchedProducts->isNotEmpty(),
                'matched_products' => $matchedProducts->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'price' => (float) $p->price,
                    'kilograms' => $p->kilograms ? (float) $p->kilograms : null,
                    'effective_kilograms' => (float) $p->effective_kilograms,
                    'unit' => $p->unit,
                    'selling_unit' => $p->unit,
                    'stock_unit_label' => $p->stock_unit_label,
                    'price_unit_label' => $p->price_unit_label,
                    'stock_with_unit' => $p->stock_with_unit,
                    'has_weight_per_unit' => $p->has_weight_per_unit,
                    'formatted_weight_per_unit' => $p->formatted_weight_per_unit,
                    'quantity_in_stock' => $p->quantity,
                    'farmer_name' => $p->farmer?->name ?? 'Local Farmer',
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
     * JSON API for Bulk Orders, scoped to the authenticated viewer.
     */
    public function bulkOrders(Request $request): JsonResponse
    {
        $viewer = $request->user();

        $query = Order::with([
            'buyer:id,name,phone_number,address',
            'farmer:id,name,phone_number,address',
            'items.product:id,name,price,unit,image_path',
            'payment',
        ])
            ->where('order_type', 'bulk');

        $this->scopeOrdersToViewer($query, $viewer);

        return response()->json([
            'status' => 'success',
            'data' => OrderResource::collection($query->latest()->get()),
        ]);
    }

    /**
     * JSON API for Farmer/Seller Location Information
     */
    public function farmerLocations(): JsonResponse
    {
        $farmers = User::where('role', 'farmer')
            ->select('id', 'name', 'bio')
            ->withCount('products')
            ->get()
            ->map(fn (User $farmer): array => [
                'id' => $farmer->id,
                'name' => $farmer->name,
                'bio' => $farmer->bio,
                'products_count' => (int) $farmer->products_count,
            ]);

        return response()->json([
            'status' => 'success',
            'data' => $farmers,
        ]);
    }

    /**
     * Constrain an order query to the records the viewer is allowed to read.
     *
     * Client-supplied buyer_id/farmer_id filters cannot widen this scope
     * because non-administrators are always constrained before any filter is
     * applied.
     *
     * Unsupported roles are rejected rather than being treated as buyers.
     *
     * @param  Builder<Order>  $query
     */
    private function scopeOrdersToViewer(Builder $query, User $viewer): void
    {
        if ($viewer->isAdmin()) {
            return;
        }

        if ($viewer->isFarmer()) {
            $query->where('farmer_id', $viewer->id);

            return;
        }

        if ($viewer->isBuyer()) {
            $query->where('buyer_id', $viewer->id);

            return;
        }

        abort(403, 'Unsupported account role.');
    }
}
