<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

        if ($order->status !== 'pending') {
            return back()->with('error', 'Only pending orders can be cancelled.');
        }

        // Return stock
        foreach ($order->items as $item) {
            Product::where('id', $item->product_id)->increment('quantity', $item->quantity);
        }

        $order->update(['status' => 'cancelled']);

        ActivityLog::log($buyer->id, 'Order Cancelled', "Buyer cancelled Order #{$order->order_number}.");

        return back()->with('success', "Order #{$order->order_number} has been cancelled.");
    }

    public function reorder(Order $order)
    {
        $buyer = Auth::user();
        if ($order->buyer_id !== $buyer->id) {
            abort(403);
        }

        $cart = session()->get('cart', []);
        $reorderedCount = 0;

        foreach ($order->items as $item) {
            $product = $item->product;
            if ($product && $product->is_active && $product->quantity >= $item->quantity) {
                $cart[$product->id] = [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => (float) $product->price,
                    'kilograms' => (float) $product->kilograms,
                    'unit' => $product->unit,
                    'image_path' => $product->image_path,
                    'farmer_name' => $product->farmer->name,
                    'farmer_id' => $product->user_id,
                    'farmer_address' => $product->farmer->address,
                    'quantity' => $item->quantity,
                    'order_type' => $order->order_type,
                ];
                $reorderedCount++;
            }
        }

        session()->put('cart', $cart);

        if ($reorderedCount > 0) {
            ActivityLog::log($buyer->id, 'Reordered Items', "Reordered items from Order #{$order->order_number}.");
            return redirect()->route('cart.index')->with('success', "Added {$reorderedCount} item(s) from Order #{$order->order_number} back to your cart!");
        }

        return back()->with('error', 'Sorry, items from this order are currently out of stock.');
    }
}
