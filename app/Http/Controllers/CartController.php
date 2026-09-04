<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CartController extends Controller
{
    public function index()
    {
        $cart = session()->get('cart', []);
        $subtotal = 0;
        $totalKilograms = 0;

        foreach ($cart as $item) {
            $subtotal += $item['price'] * $item['quantity'];
            $totalKilograms += ($item['kilograms'] ?? 1) * $item['quantity'];
        }

        return view('cart.index', compact('cart', 'subtotal', 'totalKilograms'));
    }

    public function add(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'order_type' => 'nullable|in:standard,bulk',
        ]);

        $product = Product::with('farmer')->findOrFail($request->product_id);

        if (!$product->is_active) {
            return back()->with('error', 'This product is currently inactive.');
        }

        $requestedQty = (int) $request->quantity;

        // Inventory check
        if ($product->quantity < $requestedQty) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => "Only {$product->quantity} {$product->unit}(s) available in stock.",
                ], 422);
            }
            return back()->with('error', "Insufficient stock! Only {$product->quantity} {$product->unit}(s) available.");
        }

        $cart = session()->get('cart', []);
        $productId = $product->id;

        if (isset($cart[$productId])) {
            $newQty = $cart[$productId]['quantity'] + $requestedQty;
            if ($newQty > $product->quantity) {
                if ($request->ajax()) {
                    return response()->json([
                        'success' => false,
                        'message' => "Cannot exceed available stock of {$product->quantity} {$product->unit}(s).",
                    ], 422);
                }
                return back()->with('error', "Cannot exceed available stock of {$product->quantity} {$product->unit}(s).");
            }
            $cart[$productId]['quantity'] = $newQty;
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
                'quantity' => $requestedQty,
                'order_type' => $request->get('order_type', $requestedQty >= 20 ? 'bulk' : 'standard'),
            ];
        }

        session()->put('cart', $cart);

        if (Auth::check()) {
            ActivityLog::log(Auth::id(), 'Product Added to Cart', "Added {$requestedQty}x {$product->name} to cart.");
        }

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Added {$product->name} to your cart!",
                'cart_count' => count($cart),
                'product_name' => $product->name,
                'quantity' => $requestedQty,
            ]);
        }

        return back()->with('success', "{$product->name} added to cart!");
    }

    public function update(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $product = Product::findOrFail($request->product_id);
        $cart = session()->get('cart', []);

        if (isset($cart[$product->id])) {
            if ($request->quantity > $product->quantity) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot set quantity higher than available stock ({$product->quantity}).",
                ], 422);
            }

            $cart[$product->id]['quantity'] = (int) $request->quantity;
            $cart[$product->id]['order_type'] = $request->quantity >= 20 ? 'bulk' : 'standard';
            session()->put('cart', $cart);

            $itemSubtotal = $cart[$product->id]['price'] * $cart[$product->id]['quantity'];
            $cartTotal = array_sum(array_map(fn($i) => $i['price'] * $i['quantity'], $cart));

            return response()->json([
                'success' => true,
                'item_subtotal' => number_format($itemSubtotal, 2),
                'cart_total' => number_format($cartTotal, 2),
                'cart_count' => count($cart),
            ]);
        }

        return response()->json(['success' => false, 'message' => 'Product not found in cart.'], 404);
    }

    public function remove(Request $request)
    {
        $request->validate([
            'product_id' => 'required',
        ]);

        $cart = session()->get('cart', []);

        if (isset($cart[$request->product_id])) {
            unset($cart[$request->product_id]);
            session()->put('cart', $cart);
        }

        if ($request->ajax()) {
            $cartTotal = array_sum(array_map(fn($i) => $i['price'] * $i['quantity'], $cart));
            return response()->json([
                'success' => true,
                'message' => 'Item removed from cart.',
                'cart_count' => count($cart),
                'cart_total' => number_format($cartTotal, 2),
            ]);
        }

        return back()->with('success', 'Item removed from cart.');
    }

    public function checkout()
    {
        $cart = session()->get('cart', []);
        if (empty($cart)) {
            return redirect()->route('marketplace')->with('info', 'Your cart is empty. Please add agricultural products first.');
        }

        $user = Auth::user();
        $subtotal = array_sum(array_map(fn($i) => $i['price'] * $i['quantity'], $cart));
        $totalKg = array_sum(array_map(fn($i) => ($i['kilograms'] ?? 1) * $i['quantity'], $cart));

        // Detect if contains bulk quantities
        $hasBulkItems = false;
        foreach ($cart as $item) {
            if ($item['quantity'] >= 20 || ($item['order_type'] ?? '') === 'bulk') {
                $hasBulkItems = true;
                break;
            }
        }

        return view('cart.checkout', compact('cart', 'user', 'subtotal', 'totalKg', 'hasBulkItems'));
    }

    public function placeOrder(Request $request)
    {
        $cart = session()->get('cart', []);
        if (empty($cart)) {
            return redirect()->route('marketplace')->with('error', 'Your shopping cart is empty.');
        }

        $validated = $request->validate([
            'delivery_method' => 'required|in:deliver,pickup',
            'delivery_address' => 'required_if:delivery_method,deliver|nullable|string|max:500',
            'payment_method' => 'required|in:cod,bank_transfer,gcash',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
        ]);

        $buyer = Auth::user();

        // Check stock for all items before proceeding
        foreach ($cart as $productId => $item) {
            $prod = Product::lockForUpdate()->find($productId);
            if (!$prod || $prod->quantity < $item['quantity']) {
                return back()->with('error', "Sorry, '{$item['name']}' only has {$prod?->quantity} units left. Please adjust your cart.")->withInput();
            }
        }

        // Group items by farmer so each farmer gets an order
        $farmerItems = [];
        foreach ($cart as $productId => $item) {
            $farmerId = $item['farmer_id'];
            $farmerItems[$farmerId][] = $item;
        }

        $createdOrders = [];

        DB::transaction(function () use ($farmerItems, $validated, $buyer, &$createdOrders) {
            foreach ($farmerItems as $farmerId => $items) {
                $farmerOrderSubtotal = 0;
                $isBulk = false;

                foreach ($items as $it) {
                    $farmerOrderSubtotal += $it['price'] * $it['quantity'];
                    if ($it['quantity'] >= 20 || ($it['order_type'] ?? '') === 'bulk') {
                        $isBulk = true;
                    }
                }

                $shippingFee = $validated['delivery_method'] === 'deliver' ? 60.00 : 0.00;
                $orderTotal = $farmerOrderSubtotal + $shippingFee;

                $orderNumber = ($isBulk ? 'BLK-' : 'ORD-') . date('Ymd') . '-' . strtoupper(Str::random(5));

                $order = Order::create([
                    'order_number' => $orderNumber,
                    'buyer_id' => $buyer->id,
                    'farmer_id' => $farmerId,
                    'order_type' => $isBulk ? 'bulk' : 'standard',
                    'total_amount' => $orderTotal,
                    'delivery_method' => $validated['delivery_method'],
                    'delivery_address' => $validated['delivery_method'] === 'deliver' ? $validated['delivery_address'] : 'Pick up at Farm Location',
                    'shipping_fee' => $shippingFee,
                    'status' => 'pending',
                    'notes' => $validated['notes'] ?? null,
                ]);

                foreach ($items as $it) {
                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $it['id'],
                        'quantity' => $it['quantity'],
                        'unit_price' => $it['price'],
                        'subtotal' => $it['price'] * $it['quantity'],
                    ]);

                    // Deduct product inventory
                    Product::where('id', $it['id'])->decrement('quantity', $it['quantity']);
                }

                // Create Payment record
                Payment::create([
                    'order_id' => $order->id,
                    'payment_method' => $validated['payment_method'],
                    'payment_status' => $validated['payment_method'] === 'cod' ? 'pending' : 'paid',
                    'reference_number' => $validated['reference_number'] ?? null,
                    'amount' => $orderTotal,
                    'payment_date' => now(),
                ]);

                ActivityLog::log(
                    $buyer->id,
                    $isBulk ? 'Bulk Order Placed' : 'Order Placed',
                    "Order #{$orderNumber} placed for ₱" . number_format($orderTotal, 2)
                );

                $createdOrders[] = $order;
            }
        });

        // Clear cart session
        session()->forget('cart');

        return redirect()->route('buyer.orders')->with('success', 'Your order has been placed successfully! You can track its status below.');
    }
}
