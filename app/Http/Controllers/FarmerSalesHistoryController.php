<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Support\Money;
use App\Support\Quantity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

        $sales = $salesQuery
            ->orderByRaw('COALESCE(completed_at, created_at) DESC')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $completedOrders = $this->completedOrdersQuery($farmerId);
        $completedItems = $this->completedItemsQuery($farmerId);

        $totalProductSales = Money::formatCentsForDisplay($this->sumMoneyCents(clone $completedItems, 'subtotal'));
        $completedTransactions = (clone $completedOrders)->count();
        $productsSold = Quantity::formatMilliUnits($this->sumQuantityMilliUnits(clone $completedItems));
        $shippingFees = Money::formatCentsForDisplay($this->sumMoneyCents(clone $completedOrders, 'shipping_fee'));
        $totalOrderValue = Money::formatCentsForDisplay($this->sumMoneyCents(clone $completedOrders, 'total_amount'));

        $todaySales = Money::formatCentsForDisplay($this->sumMoneyCents(
            $this->completedItemsQuery($farmerId)
                ->whereHas('order', function (Builder $query): void {
                    $query->whereDate(DB::raw('COALESCE(completed_at, created_at)'), today());
                }),
            'subtotal'
        ));

        $monthSales = Money::formatCentsForDisplay($this->sumMoneyCents(
            $this->completedItemsQuery($farmerId)
                ->whereHas('order', function (Builder $query): void {
                    $query->whereBetween(DB::raw('COALESCE(completed_at, created_at)'), [
                        now()->startOfMonth(),
                        now()->endOfMonth(),
                    ]);
                }),
            'subtotal'
        ));

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
            $query->whereDate(DB::raw('COALESCE(completed_at, created_at)'), '>=', $from);
        }

        if ($to !== null) {
            $query->whereDate(DB::raw('COALESCE(completed_at, created_at)'), '<=', $to);
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

    private function sumMoneyCents(Builder $query, string $column): int
    {
        $totalCents = 0;

        $query->select(['id', $column])
            ->chunkById(500, function (EloquentCollection $rows) use (&$totalCents, $column): void {
                foreach ($rows as $row) {
                    $totalCents += Money::cents((string) $row->getAttribute($column));
                }
            });

        return $totalCents;
    }

    private function sumQuantityMilliUnits(Builder $query): int
    {
        $totalMilliUnits = 0;

        $query->select(['id', 'quantity'])
            ->chunkById(500, function (EloquentCollection $rows) use (&$totalMilliUnits): void {
                foreach ($rows as $row) {
                    $totalMilliUnits += Quantity::milliUnits((string) $row->quantity);
                }
            });

        return $totalMilliUnits;
    }
}
