<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Notifications\OrderPlacedNotification;
use App\Support\Money;
use App\Support\Quantity;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CartController extends Controller
{
    public function index()
    {
        $cart = $this->refreshCart(session()->get('cart', []));
        session()->put('cart', $cart);

        $subtotalCents = 0;
        $totalKilograms = 0;

        foreach ($cart as $item) {
            $subtotalCents += $item['subtotal_cents'];
            $totalKilograms += ($item['kilograms'] ?? 0) * $item['quantity'];
        }

        $subtotal = Money::formatCents($subtotalCents);

        return view('cart.index', compact('cart', 'subtotal', 'totalKilograms'));
    }

    public function add(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'order_type' => 'nullable|in:standard,bulk',
        ]);

        $product = Product::with('farmer')->findOrFail($validated['product_id']);
        $quantityData = $request->validate([
            'quantity' => Quantity::validationRules($product->unit),
        ]);

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

        $requestedQty = Quantity::normalize($quantityData['quantity']);
        $requestedMilliUnits = Quantity::milliUnits($requestedQty);

        // Inventory check
        if (Quantity::milliUnits((string) $product->quantity) < $requestedMilliUnits) {
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
            $existingQuantityValidator = Validator::make(
                ['quantity' => $cart[$productId]['quantity'] ?? ''],
                ['quantity' => Quantity::validationRules($product->unit)]
            );

            if ($existingQuantityValidator->fails()) {
                return $this->cartQuantityError(
                    $request,
                    'The existing cart quantity is invalid for this product unit. Remove it and add the product again.'
                );
            }

            $newQty = Quantity::add($cart[$productId]['quantity'], $requestedQty);
            if (Quantity::milliUnits($newQty) > Quantity::milliUnits((string) $product->quantity)) {
                if ($request->ajax()) {
                    return response()->json([
                        'success' => false,
                        'message' => "Cannot exceed available stock of {$product->quantity} {$product->unit}(s).",
                    ], 422);
                }

                return back()->with('error', "Cannot exceed available stock of {$product->quantity} {$product->unit}(s).");
            }
            try {
                $cart[$productId] = $product->cartItemPayload($newQty);
            } catch (DomainException $exception) {
                return $this->cartQuantityError($request, $exception->getMessage());
            }
        } else {
            try {
                $cart[$productId] = $product->cartItemPayload(
                    $requestedQty,
                    $request->get('order_type', $requestedMilliUnits >= 20_000 ? 'bulk' : 'standard')
                );
            } catch (DomainException $exception) {
                return $this->cartQuantityError($request, $exception->getMessage());
            }
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
        ]);

        $product = Product::with('farmer')->findOrFail($request->product_id);
        $quantityData = $request->validate([
            'quantity' => Quantity::validationRules($product->unit),
        ]);
        $cart = session()->get('cart', []);

        if (isset($cart[$product->id])) {
            $quantity = Quantity::normalize($quantityData['quantity']);
            if (Quantity::milliUnits($quantity) > Quantity::milliUnits((string) $product->quantity)) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot set quantity higher than available stock ({$product->quantity}).",
                ], 422);
            }

            try {
                $cart[$product->id] = $product->cartItemPayload(
                    $quantity,
                    Quantity::milliUnits($quantity) >= 20_000 ? 'bulk' : 'standard'
                );
            } catch (DomainException $exception) {
                return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
            }
            session()->put('cart', $cart);

            $itemSubtotalCents = $cart[$product->id]['subtotal_cents'];
            $cartTotalCents = $this->cartSubtotalCents($cart);

            return response()->json([
                'success' => true,
                'item_subtotal' => Money::formatCents($itemSubtotalCents),
                'cart_total' => Money::formatCents($cartTotalCents),
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
            $cartTotal = Money::formatCents($this->cartSubtotalCents($cart));

            return response()->json([
                'success' => true,
                'message' => 'Item removed from cart.',
                'cart_count' => count($cart),
                'cart_total' => $cartTotal,
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
        $subtotalCents = $this->cartSubtotalCents($cart);
        $subtotal = Money::formatCents($subtotalCents);
        $totalKg = array_sum(array_map(fn ($i) => ($i['kilograms'] ?? 0) * $i['quantity'], $cart));

        // Detect if contains bulk quantities
        $hasBulkItems = false;
        foreach ($cart as $item) {
            if (Quantity::milliUnits($item['quantity']) >= 20_000 || ($item['order_type'] ?? '') === 'bulk') {
                $hasBulkItems = true;
                break;
            }
        }

        // Count unique farmers so the checkout can explain that each farmer sets their own delivery fee.
        $farmerIds = array_values(array_unique(array_map('intval', array_column($cart, 'farmer_id'))));
        $farmerCount = count($farmerIds);
        $selectedItems = array_keys($cart);

        // Pickup addresses are only resolved for the authenticated checkout context.
        // They are never added to the session cart payload or public product data.
        $farmers = User::query()
            ->whereIn('id', $farmerIds)
            ->get(['id', 'name', 'address'])
            ->keyBy('id');

        $pickupLocations = collect($farmerIds)
            ->map(function (int $farmerId) use ($farmers): array {
                $farmer = $farmers->get($farmerId);
                $address = trim((string) ($farmer?->address ?? ''));

                return [
                    'farmer_id' => $farmerId,
                    'farmer_name' => $farmer?->name ?? 'Local Farmer',
                    'address' => $address !== '' ? $address : null,
                ];
            })
            ->values();

        $hasMissingPickupAddress = $pickupLocations->contains(
            fn (array $location): bool => $location['address'] === null
        );

        return view('cart.checkout', compact(
            'cart',
            'user',
            'subtotal',
            'totalKg',
            'hasBulkItems',
            'farmerCount',
            'selectedItems',
            'pickupLocations',
            'hasMissingPickupAddress'
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
            $quantity = $item['quantity'] ?? '';
            if (! Quantity::isValid($quantity) || Quantity::milliUnits($quantity) < 1) {
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

                    $quantityValidator = Validator::make(
                        ['quantity' => $quantity],
                        ['quantity' => Quantity::validationRules($product->unit)]
                    );

                    if ($quantityValidator->fails()) {
                        throw new DomainException("The selected quantity for '{$product->name}' is invalid for its selling unit.");
                    }

                    $quantity = Quantity::normalize($quantity);
                    $quantityMilliUnits = Quantity::milliUnits($quantity);

                    if (Quantity::milliUnits((string) $product->quantity) < $quantityMilliUnits) {
                        throw new DomainException("Sorry, '{$product->name}' only has {$product->quantity} {$product->stock_unit_label} left. Please adjust your cart.");
                    }

                    $normalizedItems[] = [
                        'product' => $product,
                        'quantity' => $quantity,
                        'quantity_milli_units' => $quantityMilliUnits,
                    ];
                }

                $farmerItems = [];
                foreach ($normalizedItems as $line) {
                    $farmerItems[$line['product']->user_id][] = $line;
                }

                foreach ($farmerItems as $farmerId => $lines) {
                    $farmerOrderSubtotalCents = 0;
                    $isBulk = false;

                    foreach ($lines as $lineIndex => $line) {
                        $lines[$lineIndex]['subtotal_cents'] = Money::lineSubtotalCents(
                            (string) $line['product']->price,
                            $line['quantity_milli_units']
                        );
                        $farmerOrderSubtotalCents += $lines[$lineIndex]['subtotal_cents'];
                        if ($line['quantity_milli_units'] >= 20_000) {
                            $isBulk = true;
                        }
                    }

                    $orderDeliveryAddress = $validated['delivery_address'] ?? null;

                    if ($validated['delivery_method'] === 'pickup') {
                        $pickupAddress = trim((string) ($lines[0]['product']->farmer?->address ?? ''));

                        if ($pickupAddress === '') {
                            throw new DomainException('Pickup is unavailable because the farmer has not provided a pickup address. Please choose delivery or contact the farmer.');
                        }

                        $orderDeliveryAddress = $pickupAddress;
                    }

                    $shippingFeeCents = 0;
                    $orderTotalCents = $farmerOrderSubtotalCents + $shippingFeeCents;
                    Money::ensureOrderTotalFits($orderTotalCents);
                    $orderTotal = Money::formatCents($orderTotalCents);
                    $orderNumber = ($isBulk ? 'BLK-' : 'ORD-').date('Ymd').'-'.strtoupper(Str::random(5));

                    $order = Order::create([
                        'order_number' => $orderNumber,
                        'buyer_id' => $buyer->id,
                        'farmer_id' => $farmerId,
                        'order_type' => $isBulk ? 'bulk' : 'standard',
                        'total_amount' => $orderTotal,
                        'delivery_method' => $validated['delivery_method'],
                        'delivery_address' => $orderDeliveryAddress,
                        'shipping_fee' => Money::formatCents($shippingFeeCents),
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
                            'subtotal' => Money::formatCents($line['subtotal_cents']),
                        ]);
                    }

                    Payment::create([
                        'order_id' => $order->id,
                        'payment_method' => $dbPaymentMethod,
                        'payment_status' => 'pending',
                        'reference_number' => $validated['reference_number'] ?? null,
                        'amount' => $orderTotal,
                        'payment_date' => null,
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

            $quantity = $item['quantity'] ?? '';

            if (! Quantity::isValid($quantity) || Quantity::milliUnits($quantity) < 1) {
                unset($cart[$productId]);
                session()->flash('error', 'An item with an invalid cart quantity was removed. Please add it again.');

                continue;
            }

            if (! Quantity::isWeightUnit($product->unit) && Quantity::milliUnits($quantity) % 1000 !== 0) {
                unset($cart[$productId]);
                session()->flash('error', 'A fractional quantity is not valid for one of your cart items.');

                continue;
            }

            try {
                $cart[$productId] = $product->cartItemPayload(
                    Quantity::normalize($quantity),
                    $item['order_type'] ?? null
                );
            } catch (DomainException $exception) {
                unset($cart[$productId]);
                session()->flash('error', $exception->getMessage());
            }
        }

        return $cart;
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $cart
     */
    private function cartSubtotalCents(array $cart): int
    {
        $subtotalCents = 0;

        foreach ($cart as $item) {
            $subtotalCents += (int) $item['subtotal_cents'];
        }

        return $subtotalCents;
    }

    private function cartQuantityError(Request $request, string $message)
    {
        if ($request->ajax()) {
            return response()->json(['success' => false, 'message' => $message], 422);
        }

        return back()->with('error', $message);
    }
}
