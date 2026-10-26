<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FarmerSalesHistoryController extends Controller
{
    public function index(Request $request)
    {
        $farmerId = (int) Auth::id();

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:190'],
            'status' => ['nullable', Rule::in(['completed', 'delivered', 'picked_up', 'pending', 'confirmed', 'cancelled', 'all'])],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $status = $validated['status'] ?? 'completed';
        $search = trim((string) ($validated['search'] ?? ''));
        $from = $validated['from'] ?? null;
        $to = $validated['to'] ?? null;

        if ($from !== null && $to !== null && Carbon::parse($to)->lt(Carbon::parse($from))) {
            throw ValidationException::withMessages([
                'to' => 'The end date must be after or equal to the start date.',
            ]);
        }

        $salesQuery = Order::query()
            ->with(['buyer', 'items.product', 'payment'])
            ->where('farmer_id', $farmerId);

        $this->applyFilters($salesQuery, $status, $search, $from, $to);

        $sales = $salesQuery->latest()->paginate(15)->withQueryString();

        $completedOrders = $this->completedOrdersQuery($farmerId);
        $completedItems = $this->completedItemsQuery($farmerId);

        $totalProductSales = (float) (clone $completedItems)->sum('subtotal');
        $completedTransactions = (clone $completedOrders)->count();
        $productsSold = (int) (clone $completedItems)->sum('quantity');
        $shippingFees = (float) (clone $completedOrders)->sum('shipping_fee');
        $totalOrderValue = (float) (clone $completedOrders)->sum('total_amount');

        $todaySales = (float) $this->completedItemsQuery($farmerId)
            ->whereHas('order', function (Builder $query): void {
                $query->whereDate('created_at', today());
            })
            ->sum('subtotal');

        $monthSales = (float) $this->completedItemsQuery($farmerId)
            ->whereHas('order', function (Builder $query): void {
                $query->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]);
            })
            ->sum('subtotal');

        $hasFilters = $search !== '' || $from !== null || $to !== null || $status !== 'completed';

        return view('farmer.sales.index', compact(
            'sales',
            'status',
            'search',
            'from',
            'to',
            'hasFilters',
            'totalProductSales',
            'completedTransactions',
            'productsSold',
            'shippingFees',
            'totalOrderValue',
            'todaySales',
            'monthSales'
        ));
    }

    public function show(Order $order)
    {
        abort_unless($order->farmer_id === Auth::id(), 403);

        $order->load(['buyer', 'items.product.category', 'payment']);

        return view('farmer.sales.show', compact('order'));
    }

    private function applyFilters(Builder $query, string $status, string $search, ?string $from, ?string $to): void
    {
        if ($status === 'completed') {
            $query->whereIn('status', Order::COMPLETED_STATUSES);
        } elseif ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->where(function (Builder $query) use ($search): void {
                $query->where('order_number', 'LIKE', "%{$search}%")
                    ->orWhereHas('items.product', function (Builder $productQuery) use ($search): void {
                        $productQuery->where('name', 'LIKE', "%{$search}%");
                    });
            });
        }

        if ($from !== null) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to !== null) {
            $query->whereDate('created_at', '<=', $to);
        }
    }

    private function completedOrdersQuery(int $farmerId): Builder
    {
        return Order::query()
            ->where('farmer_id', $farmerId)
            ->whereIn('status', Order::COMPLETED_STATUSES);
    }

    private function completedItemsQuery(int $farmerId): Builder
    {
        return OrderItem::query()
            ->whereHas('order', function (Builder $query) use ($farmerId): void {
                $query->where('farmer_id', $farmerId)
                    ->whereIn('status', Order::COMPLETED_STATUSES);
            });
    }
}
