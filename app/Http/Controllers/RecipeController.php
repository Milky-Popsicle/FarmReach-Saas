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
        $validated = $request->validate([
            'products' => 'nullable|array',
            'products.*.id' => 'nullable|integer|exists:products,id',
            'products.*.quantity' => 'nullable|integer|min:1',
        ]);

        $requestedProducts = collect($validated['products'] ?? [])
            ->filter(fn (mixed $item): bool => is_array($item) && isset($item['id'], $item['quantity']));

        if ($requestedProducts->isEmpty()) {
            $message = 'Please select at least one ingredient to add to your cart.';

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $message], 422);
            }

            return back()->with('error', $message);
        }

        $products = Product::with('farmer')
            ->whereIn('id', $requestedProducts->pluck('id'))
            ->get()
            ->keyBy('id');

        $cart = session()->get('cart', []);
        $addedCount = 0;
        $skippedCount = 0;

        foreach ($requestedProducts as $item) {
            $product = $products->get((int) $item['id']);
            $quantity = (int) $item['quantity'];

            if (! $product || ! $product->is_active) {
                $skippedCount++;

                continue;
            }

            if (Auth::check() && $product->user_id === Auth::id()) {
                $skippedCount++;

                continue;
            }

            $existingQuantity = (int) ($cart[$product->id]['quantity'] ?? 0);
            $newQuantity = $existingQuantity + $quantity;

            if ($newQuantity > $product->quantity) {
                $skippedCount++;

                continue;
            }

            $cart[$product->id] = $product->cartItemPayload($newQuantity);
            $addedCount++;
        }

        if ($addedCount === 0) {
            $message = 'No selected ingredients could be added. They may be inactive, out of stock, or your own listing.';

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $message], 422);
            }

            return back()->with('error', $message);
        }

        session()->put('cart', $cart);

        if (Auth::check()) {
            ActivityLog::log(Auth::id(), 'Recipe Ingredients Added to Cart', "Added {$addedCount} recipe ingredients to shopping cart.");
        }

        $message = "Successfully added {$addedCount} ingredient(s) to your cart!";
        if ($skippedCount > 0) {
            $message .= " {$skippedCount} item(s) were skipped because they were unavailable or exceeded available stock.";
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'cart_count' => count($cart),
            ]);
        }

        return redirect()->route('cart.index')->with('success', $message);
    }
}
