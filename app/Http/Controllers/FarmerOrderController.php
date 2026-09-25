<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FarmerOrderController extends Controller
{
    public function index(Request $request)
    {
        $farmer = Auth::user();

        $query = Order::with(['buyer', 'items.product', 'payment'])
            ->where('farmer_id', $farmer->id);

        // Filter by order type: standard vs bulk
        if ($request->filled('type') && in_array($request->type, ['standard', 'bulk'])) {
            $query->where('order_type', $request->type);
        }

        // Filter by status
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $orders = $query->latest()->paginate(15)->withQueryString();

        $standardOrdersCount = Order::where('farmer_id', $farmer->id)->where('order_type', 'standard')->count();
        $bulkOrdersCount = Order::where('farmer_id', $farmer->id)->where('order_type', 'bulk')->count();
        $pendingOrdersCount = Order::where('farmer_id', $farmer->id)->where('status', 'pending')->count();
        $totalSales = Order::where('farmer_id', $farmer->id)->whereIn('status', ['delivered', 'picked_up'])->sum('total_amount');

        return view('farmer.orders', compact(
            'orders',
            'standardOrdersCount',
            'bulkOrdersCount',
            'pendingOrdersCount',
            'totalSales'
        ));
    }

    public function updateStatus(Request $request, Order $order)
    {
        $farmer = Auth::user();
        if ($order->farmer_id !== $farmer->id) {
            abort(403);
        }

        $validated = $request->validate([
            'status' => 'required|in:confirmed,delivered,picked_up,cancelled',
            'shipping_fee' => 'nullable|numeric|min:0',
        ]);

        $targetStatus = $validated['status'];
        if ($order->delivery_method === 'pickup' && $targetStatus === 'delivered') {
            $targetStatus = 'picked_up';
        }

        $oldStatus = $order->status;
        $order->status = $targetStatus;

        // If confirming a delivery order and farmer sets shipping fee, update order totals
        if ($targetStatus === 'confirmed' && $order->delivery_method === 'deliver' && $request->filled('shipping_fee')) {
            $newShippingFee = (float) $request->shipping_fee;
            $itemsTotal = $order->items->sum('subtotal');
            $order->shipping_fee = $newShippingFee;
            $order->total_amount = $itemsTotal + $newShippingFee;

            if ($order->payment) {
                $order->payment->update(['amount' => $order->total_amount]);
            }
        }

        $order->save();

        // If marked delivered or picked up, also mark payment paid
        if (in_array($targetStatus, ['delivered', 'picked_up'], true) && $order->payment) {
            $order->payment->update(['payment_status' => 'paid']);
        }

        ActivityLog::log(
            $farmer->id,
            'Order Status Updated',
            "Farmer updated Order #{$order->order_number} status from {$oldStatus} to {$order->status_label}."
        );

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Order #{$order->order_number} status updated to {$order->status_label}",
                'new_status' => $targetStatus,
                'status_label' => $order->status_label,
                'badge_class' => $order->status_badge_class,
                'total_amount' => number_format($order->total_amount, 2),
                'shipping_fee' => number_format($order->shipping_fee, 2),
            ]);
        }

        return back()->with('success', "Order #{$order->order_number} updated to {$order->status_label}");
    }
}
