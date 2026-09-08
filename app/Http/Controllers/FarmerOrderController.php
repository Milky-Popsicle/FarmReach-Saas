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
        $totalSales = Order::where('farmer_id', $farmer->id)->where('status', 'delivered')->sum('total_amount');

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
            'status' => 'required|in:confirmed,delivered,cancelled',
        ]);

        $oldStatus = $order->status;
        $order->update(['status' => $validated['status']]);

        // If marked delivered, also mark payment paid
        if ($validated['status'] === 'delivered' && $order->payment) {
            $order->payment->update(['payment_status' => 'paid']);
        }

        ActivityLog::log(
            $farmer->id,
            'Order Status Updated',
            "Farmer updated Order #{$order->order_number} status from {$oldStatus} to {$validated['status']}."
        );

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Order #{$order->order_number} status updated to ".ucfirst($validated['status']),
                'new_status' => $validated['status'],
                'badge_class' => $order->status_badge_class,
            ]);
        }

        return back()->with('success', "Order #{$order->order_number} updated to ".ucfirst($validated['status']));
    }
}
