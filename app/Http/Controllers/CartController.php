<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Notifications\OrderPlacedNotification;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CartController extends Controller
{
    public function index()
    {
        $cart = $this->refreshCart(session()->get('cart', []));
        session()->put('cart', $cart);

        $subtotal = 0;
        $totalKilograms = 0;

        foreach ($cart as $item) {
            $subtotal += $item['price'] * $item['quantity'];
            $totalKilograms += ($item['kilograms'] ?? 0) * $item['quantity'];
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

        if (! $product->is_active) {
            return back()->with('error', 'This product is currently inactive.');
        }

        // Block farmers from buying their own products
        if (Auth::check() && $product->user_id === Auth::id()) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => "You cannot purchase your own crop listing ('{$product->name}').",
                ], 422);
            }

            return back()->with('error', "You cannot purchase your own crop listing ('{$product->name}').");
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
            $cart[$productId] = $product->cartItemPayload($newQty);
        } else {
            $cart[$productId] = $product->cartItemPayload(
                $requestedQty,
                $request->get('order_type', $requestedQty >= 20 ? 'bulk' : 'standard')
            );
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

        $product = Product::with('farmer')->findOrFail($request->product_id);
        $cart = session()->get('cart', []);

        if (isset($cart[$product->id])) {
            if ($request->quantity > $product->quantity) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot set quantity higher than available stock ({$product->quantity}).",
                ], 422);
            }

            $cart[$product->id] = $product->cartItemPayload(
                (int) $request->quantity,
                $request->quantity >= 20 ? 'bulk' : 'standard'
            );
            session()->put('cart', $cart);

            $itemSubtotal = $cart[$product->id]['price'] * $cart[$product->id]['quantity'];
            $cartTotal = array_sum(array_map(fn ($i) => $i['price'] * $i['quantity'], $cart));

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
            $cartTotal = array_sum(array_map(fn ($i) => $i['price'] * $i['quantity'], $cart));

            return response()->json([
                'success' => true,
                'message' => 'Item removed from cart.',
                'cart_count' => count($cart),
                'cart_total' => number_format($cartTotal, 2),
            ]);
        }

        return back()->with('success', 'Item removed from cart.');
    }

    public function checkout(Request $request)
    {
        $cart = $this->refreshCart(session()->get('cart', []));
        session()->put('cart', $cart);

        if (empty($cart)) {
            return redirect()->route('marketplace')->with('info', 'Your cart is empty. Please add agricultural products first.');
        }

        // Filter to selected items if specified
        $selectedIds = $request->input('selected_items');
        if (! empty($selectedIds) && is_array($selectedIds)) {
            $cart = array_intersect_key($cart, array_flip($selectedIds));
        }

        if (empty($cart)) {
            return redirect()->route('cart.index')->with('error', 'Please select at least one crop from your cart to proceed to checkout.');
        }

        $user = Auth::user();
        $subtotal = array_sum(array_map(fn ($i) => $i['price'] * $i['quantity'], $cart));
        $totalKg = array_sum(array_map(fn ($i) => ($i['kilograms'] ?? 0) * $i['quantity'], $cart));

        // Detect if contains bulk quantities
        $hasBulkItems = false;
        foreach ($cart as $item) {
            if ($item['quantity'] >= 20 || ($item['order_type'] ?? '') === 'bulk') {
                $hasBulkItems = true;
                break;
            }
        }

        // Calculate unique farmers and multi-farmer shipping fee
        $farmerIds = array_unique(array_column($cart, 'farmer_id'));
        $farmerCount = count($farmerIds);
        $shippingPerFarmer = 60.00;
        $shippingTotal = $farmerCount * $shippingPerFarmer;
        $selectedItems = array_keys($cart);

        return view('cart.checkout', compact(
            'cart',
            'user',
            'subtotal',
            'totalKg',
            'hasBulkItems',
            'farmerCount',
            'shippingPerFarmer',
            'shippingTotal',
            'selectedItems'
        ));
    }

    public function placeOrder(Request $request)
    {
        $cart = $this->refreshCart(session()->get('cart', []));
        session()->put('cart', $cart);

        if (empty($cart)) {
            return redirect()->route('marketplace')->with('error', 'Your shopping cart is empty.');
        }

        $selectedIds = $request->input('selected_items');
        $itemsToOrder = $cart;
        if (! empty($selectedIds) && is_array($selectedIds)) {
            $itemsToOrder = array_intersect_key($cart, array_flip($selectedIds));
        }

        if (empty($itemsToOrder)) {
            return redirect()->route('cart.index')->with('error', 'No valid crops selected for checkout.');
        }

        $validated = $request->validate([
            'delivery_method' => 'required|in:deliver,pickup',
            'delivery_address' => 'required_if:delivery_method,deliver|nullable|string|max:500',
            'payment_method' => 'required|in:cod,cop,bank_transfer,gcash',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
            'selected_items' => 'nullable|array',
            'selected_items.*' => 'integer',
        ]);

        $dbPaymentMethod = 'cod';
        if (in_array($validated['payment_method'], ['bank_transfer', 'gcash'], true)) {
            $dbPaymentMethod = $validated['payment_method'];
        }

        $requestedItems = [];
        foreach ($itemsToOrder as $productId => $item) {
            $quantity = (int) ($item['quantity'] ?? 0);
            if ($quantity < 1) {
                return redirect()->route('cart.index')->with('error', 'One of the selected items has an invalid quantity.');
            }

            $requestedItems[(int) $productId] = $quantity;
        }

        if (empty($requestedItems)) {
            return redirect()->route('cart.index')->with('error', 'No valid crops selected for checkout.');
        }

        $buyer = Auth::user();

        $createdOrders = [];
        $decrementedProductIds = [];

        try {
            DB::transaction(function () use ($requestedItems, $validated, $dbPaymentMethod, $buyer, &$createdOrders, &$decrementedProductIds) {
                $products = Product::with('farmer')
                    ->whereIn('id', array_keys($requestedItems))
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                $normalizedItems = [];
                foreach ($requestedItems as $productId => $quantity) {
                    $product = $products->get($productId);

                    if (! $product) {
                        throw new DomainException('One of the selected products is no longer available. Please refresh your cart.');
                    }

                    if (! $product->is_active) {
                        throw new DomainException("'{$product->name}' is no longer available.");
                    }

                    if ($product->user_id === $buyer->id) {
                        throw new DomainException("You cannot place an order for your own farm produce ('{$product->name}').");
                    }

                    if ($product->quantity < $quantity) {
                        throw new DomainException("Sorry, '{$product->name}' only has {$product->quantity} {$product->stock_unit_label} left. Please adjust your cart.");
                    }

                    $normalizedItems[] = [
                        'product' => $product,
                        'quantity' => $quantity,
                    ];
                }

                $farmerItems = [];
                foreach ($normalizedItems as $line) {
                    $farmerItems[$line['product']->user_id][] = $line;
                }

                foreach ($farmerItems as $farmerId => $lines) {
                    $farmerOrderSubtotal = 0;
                    $isBulk = false;

                    foreach ($lines as $line) {
                        $farmerOrderSubtotal += (float) $line['product']->price * $line['quantity'];
                        if ($line['quantity'] >= 20) {
                            $isBulk = true;
                        }
                    }

                    $shippingFee = $validated['delivery_method'] === 'deliver' ? 60.00 : 0.00;
                    $orderTotal = $farmerOrderSubtotal + $shippingFee;
                    $orderNumber = ($isBulk ? 'BLK-' : 'ORD-').date('Ymd').'-'.strtoupper(Str::random(5));

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

                    foreach ($lines as $line) {
                        $affected = Product::whereKey($line['product']->id)
                            ->where('quantity', '>=', $line['quantity'])
                            ->decrement('quantity', $line['quantity']);

                        if ($affected < 1) {
                            throw new DomainException("'{$line['product']->name}' no longer has enough stock. Please adjust your cart.");
                        }

                        $decrementedProductIds[] = $line['product']->id;

                        OrderItem::create([
                            'order_id' => $order->id,
                            'product_id' => $line['product']->id,
                            'quantity' => $line['quantity'],
                            'unit_price' => $line['product']->price,
                            'subtotal' => (float) $line['product']->price * $line['quantity'],
                        ]);
                    }

                    Payment::create([
                        'order_id' => $order->id,
                        'payment_method' => $dbPaymentMethod,
                        'payment_status' => 'pending',
                        'reference_number' => $validated['reference_number'] ?? null,
                        'amount' => $orderTotal,
                        'payment_date' => now(),
                    ]);

                    ActivityLog::log(
                        $buyer->id,
                        $isBulk ? 'Bulk Order Placed' : 'Order Placed',
                        "Order #{$orderNumber} placed for ₱".number_format($orderTotal, 2)
                    );

                    $createdOrders[] = [
                        'order' => $order,
                        'itemCount' => count($lines),
                    ];
                }
            });
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage())->withInput();
        }

        foreach ($createdOrders as $createdOrder) {
            $order = $createdOrder['order'];

            $order->farmer?->notify(new OrderPlacedNotification(
                orderNumber: $order->order_number,
                buyerName: $buyer->name,
                totalAmount: (float) $order->total_amount,
                itemCount: $createdOrder['itemCount'],
            ));
        }

        Product::whereIn('id', array_unique($decrementedProductIds))
            ->get()
            ->each(fn (Product $product) => $product->syncLowStockNotification());

        foreach (array_keys($itemsToOrder) as $orderedId) {
            unset($cart[$orderedId]);
        }

        if (empty($cart)) {
            session()->forget('cart');
        } else {
            session()->put('cart', $cart);
        }

        return redirect()->route('buyer.orders')->with('success', 'Your order has been placed successfully! You can track its status below.');
    }

    /**
     * Refresh session cart prices and product metadata from the database.
     *
     * @param  array<int|string, array<string, mixed>>  $cart
     * @return array<int, array<string, mixed>>
     */
    private function refreshCart(array $cart): array
    {
        if (empty($cart)) {
            return [];
        }

        $productIds = array_filter(array_keys($cart), fn (int|string $id): bool => is_numeric($id));

        $products = Product::with('farmer')
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        foreach ($cart as $productId => $item) {
            $product = $products->get($productId);

            if (! $product) {
                unset($cart[$productId]);

                continue;
            }

            $quantity = max(1, (int) ($item['quantity'] ?? 1));
            $cart[$productId] = $product->cartItemPayload(
                $quantity,
                $item['order_type'] ?? null
            );
        }

        return $cart;
    }
}
