<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\Product;
use App\Notifications\OrderCancelledNotification;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BuyerOrderController extends Controller
{
    public function index(Request $request)
    {
        $buyer = Auth::user();

        $query = Order::with(['farmer', 'items.product', 'payment'])
            ->where('buyer_id', $buyer->id);

        // Status filter
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Search by Order ID or Product name
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'LIKE', "%{$search}%")
                    ->orWhereHas('items.product', function ($pq) use ($search) {
                        $pq->where('name', 'LIKE', "%{$search}%");
                    })
                    ->orWhereHas('farmer', function ($fq) use ($search) {
                        $fq->where('name', 'LIKE', "%{$search}%");
                    });
            });
        }

        $orders = $query->latest()->get();

        // Order counts for sidebar summary
        $totalOrdersCount = Order::where('buyer_id', $buyer->id)->count();
        $pendingCount = Order::where('buyer_id', $buyer->id)->where('status', 'pending')->count();
        $confirmedCount = Order::where('buyer_id', $buyer->id)->where('status', 'confirmed')->count();
        $deliveredCount = Order::where('buyer_id', $buyer->id)->where('status', 'delivered')->count();
        $cancelledCount = Order::where('buyer_id', $buyer->id)->where('status', 'cancelled')->count();

        // Recommended products
        $recommendedProducts = Product::with('farmer')
            ->where('is_active', true)
            ->where('quantity', '>', 0)
            ->inRandomOrder()
            ->take(4)
            ->get();

        $currentView = $request->get('view', 'card'); // 'card' or 'table'
        $currentStatus = $request->get('status', 'all');

        return view('buyer.orders', compact(
            'orders',
            'totalOrdersCount',
            'pendingCount',
            'confirmedCount',
            'deliveredCount',
            'cancelledCount',
            'recommendedProducts',
            'currentView',
            'currentStatus'
        ));
    }

    public function cancel(Order $order)
    {
        $buyer = Auth::user();
        if ($order->buyer_id !== $buyer->id) {
            abort(403);
        }

        try {
            $restockedProductIds = [];

            DB::transaction(function () use ($order, $buyer, &$restockedProductIds) {
                $lockedOrder = Order::with('items')
                    ->lockForUpdate()
                    ->findOrFail($order->id);

                if ($lockedOrder->status !== 'pending') {
                    throw new DomainException('Only pending orders can be cancelled.');
                }

                foreach ($lockedOrder->items as $item) {
                    Product::whereKey($item->product_id)->increment('quantity', $item->quantity);
                    $restockedProductIds[] = $item->product_id;
                }

                $lockedOrder->update(['status' => 'cancelled']);

                ActivityLog::log($buyer->id, 'Order Cancelled', "Buyer cancelled Order #{$lockedOrder->order_number}.");
            });
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $order->farmer?->notify(new OrderCancelledNotification(
            orderNumber: $order->order_number,
            cancelledBy: 'buyer',
            counterpartName: $buyer->name,
        ));

        if ($restockedProductIds !== []) {
            Product::whereIn('id', array_unique($restockedProductIds))
                ->get()
                ->each(fn (Product $product) => $product->resetLowStockNotificationIfReplenished());
        }

        return back()->with('success', "Order #{$order->order_number} has been cancelled.");
    }

    public function reorder(Order $order)
    {
        $buyer = Auth::user();
        if ($order->buyer_id !== $buyer->id) {
            abort(403);
        }

        $order->loadMissing('items.product.farmer');

        $cart = session()->get('cart', []);
        $reorderedCount = 0;

        foreach ($order->items as $item) {
            $product = $item->product;

            if (! $product || ! $product->is_active || $product->user_id === $buyer->id) {
                continue;
            }

            $existingQuantity = (int) ($cart[$product->id]['quantity'] ?? 0);
            $newQuantity = $existingQuantity + $item->quantity;

            if ($newQuantity > $product->quantity) {
                continue;
            }

            $cart[$product->id] = $product->cartItemPayload($newQuantity, $order->order_type);
            $reorderedCount++;
        }

        session()->put('cart', $cart);

        if ($reorderedCount > 0) {
            ActivityLog::log($buyer->id, 'Reordered Items', "Reordered items from Order #{$order->order_number}.");

            return redirect()->route('cart.index')->with('success', "Added {$reorderedCount} item(s) from Order #{$order->order_number} back to your cart!");
        }

        return back()->with('error', 'Sorry, items from this order are currently out of stock.');
    }

    public function destroy(Order $order)
    {
        $buyer = Auth::user();
        if ($order->buyer_id !== $buyer->id) {
            abort(403);
        }

        if ($order->status !== 'cancelled') {
            return back()->with('error', 'Only cancelled orders can be deleted.');
        }

        $orderNumber = $order->order_number;
        $order->delete();

        ActivityLog::log($buyer->id, 'Order Deleted', "Buyer deleted cancelled Order #{$orderNumber}.");

        return back()->with('success', "Cancelled order #{$orderNumber} has been deleted.");
    }

    public function bulkDestroy(Request $request)
    {
        $buyer = Auth::user();

        $validated = $request->validate([
            'order_ids' => 'required|array|min:1',
            'order_ids.*' => 'integer|exists:orders,id',
        ]);

        $orders = Order::where('buyer_id', $buyer->id)
            ->where('status', 'cancelled')
            ->whereIn('id', $validated['order_ids'])
            ->get();

        $count = $orders->count();
        if ($count === 0) {
            return back()->with('error', 'No eligible cancelled orders selected to delete.');
        }

        foreach ($orders as $order) {
            $order->delete();
        }

        ActivityLog::log($buyer->id, 'Cancelled Orders Bulk Deleted', "Buyer deleted {$count} cancelled order(s).");

        return back()->with('success', "Successfully deleted {$count} cancelled order(s).");
    }
}
