<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\Product;
use App\Notifications\OrderCancelledNotification;
use App\Notifications\OrderStatusChangedNotification;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
        $totalSales = Order::where('farmer_id', $farmer->id)->whereIn('status', Order::COMPLETED_STATUSES)->sum('total_amount');

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
            'shipping_fee' => 'nullable|numeric|min:0|max:1000|decimal:0,2',
        ]);

        $targetStatus = $validated['status'];
        if ($order->delivery_method === 'pickup' && $targetStatus === 'delivered') {
            $targetStatus = 'picked_up';
        }
        if ($order->delivery_method === 'deliver' && $targetStatus === 'picked_up') {
            $targetStatus = 'delivered';
        }

        $restockedProductIds = [];

        try {
            $order = DB::transaction(function () use ($order, $targetStatus, $request, $validated, $farmer, &$restockedProductIds) {
                $lockedOrder = Order::with(['items', 'payment'])
                    ->lockForUpdate()
                    ->findOrFail($order->id);

                $allowedTransitions = [
                    'pending' => ['confirmed', 'cancelled'],
                    'confirmed' => ['delivered', 'picked_up', 'cancelled'],
                ];

                if (! in_array($targetStatus, $allowedTransitions[$lockedOrder->status] ?? [], true)) {
                    throw new DomainException("Order #{$lockedOrder->order_number} cannot be changed from {$lockedOrder->status_label} to {$targetStatus}.");
                }

                $oldStatus = $lockedOrder->status;

                if ($targetStatus === 'cancelled') {
                    foreach ($lockedOrder->items as $item) {
                        Product::whereKey($item->product_id)->increment('quantity', $item->quantity);
                        $restockedProductIds[] = $item->product_id;
                    }
                }

                if ($targetStatus === 'confirmed' && $lockedOrder->delivery_method === 'deliver' && $request->filled('shipping_fee')) {
                    $shippingFee = round((float) $validated['shipping_fee'], 2);
                    $currentShippingFee = round((float) $lockedOrder->shipping_fee, 2);

                    if ($lockedOrder->payment?->payment_status === 'paid') {
                        if (abs($shippingFee - $currentShippingFee) > 0.001) {
                            throw new DomainException('Shipping fee cannot be changed after payment has been received.');
                        }
                    } else {
                        $itemsTotal = round((float) $lockedOrder->items->sum('subtotal'), 2);

                        $lockedOrder->shipping_fee = $shippingFee;
                        $lockedOrder->total_amount = round($itemsTotal + $shippingFee, 2);

                        if ($lockedOrder->payment) {
                            $lockedOrder->payment->update(['amount' => $lockedOrder->total_amount]);
                        }
                    }
                }

                $lockedOrder->status = $targetStatus;
                $lockedOrder->save();

                if (in_array($targetStatus, ['delivered', 'picked_up'], true) && $lockedOrder->payment) {
                    $lockedOrder->payment->update(['payment_status' => 'paid']);
                }

                ActivityLog::log(
                    $farmer->id,
                    'Order Status Updated',
                    "Farmer updated Order #{$lockedOrder->order_number} status from {$oldStatus} to {$lockedOrder->status_label}."
                );

                return $lockedOrder;
            });
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $buyer = $order->buyer;
        $buyerName = $buyer?->name ?? 'Buyer';

        if ($targetStatus === 'confirmed') {
            $shippingFee = $order->delivery_method === 'deliver' ? (float) $order->shipping_fee : null;
            $totalAmount = $shippingFee !== null ? (float) $order->total_amount : null;

            $buyer?->notify(new OrderStatusChangedNotification(
                orderNumber: $order->order_number,
                status: $targetStatus,
                statusLabel: $order->status_label,
                audience: 'buyer',
                farmerName: $farmer->name,
                buyerName: $buyerName,
                shippingFee: $shippingFee,
                totalAmount: $totalAmount,
            ));
        } elseif (in_array($targetStatus, Order::COMPLETED_STATUSES, true)) {
            $buyer?->notify(new OrderStatusChangedNotification(
                orderNumber: $order->order_number,
                status: $targetStatus,
                statusLabel: $order->status_label,
                audience: 'buyer',
                farmerName: $farmer->name,
                buyerName: $buyerName,
            ));

            $farmer->notify(new OrderStatusChangedNotification(
                orderNumber: $order->order_number,
                status: $targetStatus,
                statusLabel: $order->status_label,
                audience: 'farmer',
                farmerName: $farmer->name,
                buyerName: $buyerName,
            ));
        } elseif ($targetStatus === 'cancelled') {
            $buyer?->notify(new OrderCancelledNotification(
                orderNumber: $order->order_number,
                cancelledBy: 'farmer',
                counterpartName: $farmer->name,
            ));
        }

        if ($restockedProductIds !== []) {
            Product::whereIn('id', array_unique($restockedProductIds))
                ->get()
                ->each(fn (Product $product) => $product->resetLowStockNotificationIfReplenished());
        }

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Order #{$order->order_number} status updated to {$order->status_label}",
                'new_status' => $order->status,
                'status_label' => $order->status_label,
                'badge_class' => $order->status_badge_class,
                'total_amount' => number_format($order->total_amount, 2),
                'shipping_fee' => number_format($order->shipping_fee, 2),
            ]);
        }

        return back()->with('success', "Order #{$order->order_number} updated to {$order->status_label}");
    }
}
