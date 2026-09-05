<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\Recipe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RecipeController extends Controller
{
    public function index(Request $request)
    {
        $query = Recipe::with('ingredients');

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where('title', 'LIKE', "%{$search}%")
                ->orWhere('description', 'LIKE', "%{$search}%")
                ->orWhereHas('ingredients', function ($iq) use ($search) {
                    $iq->where('name', 'LIKE', "%{$search}%");
                });
        }

        $recipes = $query->latest()->paginate(9)->withQueryString();

        return view('recipes.index', compact('recipes'));
    }

    public function show(Recipe $recipe)
    {
        $recipe->load(['ingredients.category']);

        // Build matching for each ingredient
        $matchedIngredients = [];
        $availableCount = 0;
        $unavailableCount = 0;

        foreach ($recipe->ingredients as $ingredient) {
            $matchingProducts = $ingredient->getAvailableProducts();

            $isAvailable = $matchingProducts->isNotEmpty();
            if ($isAvailable) {
                $availableCount++;
            } else {
                $unavailableCount++;
            }

            $matchedIngredients[] = [
                'ingredient' => $ingredient,
                'is_available' => $isAvailable,
                'products' => $matchingProducts,
            ];
        }

        return view('recipes.show', compact('recipe', 'matchedIngredients', 'availableCount', 'unavailableCount'));
    }

    public function addBatchToCart(Request $request)
    {
        $request->validate([
            'products' => 'required|array|min:1',
            'products.*.id' => 'required|exists:products,id',
            'products.*.quantity' => 'required|integer|min:1',
        ]);

        $cart = session()->get('cart', []);
        $addedCount = 0;

        foreach ($request->products as $item) {
            $productId = $item['id'];
            $qty = (int) $item['quantity'];
            $product = Product::find($productId);

            if ($product && $product->is_active && $product->quantity >= $qty) {
                // Skip farmer's own products
                if (Auth::check() && $product->user_id === Auth::id()) {
                    continue;
                }
                if (isset($cart[$productId])) {
                    $cart[$productId]['quantity'] += $qty;
                } else {
                    $cart[$productId] = [
                        'id' => $product->id,
                        'name' => $product->name,
                        'price' => (float) $product->price,
                        'kilograms' => (float) $product->kilograms,
                        'unit' => $product->unit,
                        'image_path' => $product->image_path,
                        'farmer_name' => $product->farmer->name,
                        'farmer_id' => $product->user_id,
                        'farmer_address' => $product->farmer->address,
                        'quantity' => $qty,
                    ];
                }
                $addedCount++;
            }
        }

        session()->put('cart', $cart);

        if (Auth::check()) {
            ActivityLog::log(Auth::id(), 'Recipe Ingredients Added to Cart', "Added {$addedCount} recipe ingredients to shopping cart.");
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Successfully added {$addedCount} ingredient(s) to your cart!",
                'cart_count' => count($cart),
            ]);
        }

        return redirect()->route('cart.index')->with('success', "Successfully added {$addedCount} ingredient(s) to your cart!");
    }
}
