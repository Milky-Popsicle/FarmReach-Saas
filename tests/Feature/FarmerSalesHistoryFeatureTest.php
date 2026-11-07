<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FarmerSalesHistoryFeatureTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_farmer_sees_only_their_completed_sales_in_the_default_view(): void
    {
        $farmer = User::factory()->farmer()->create();
        $otherFarmer = User::factory()->farmer()->create();
        $buyer = User::factory()->buyer()->create();

        $rice = $this->createProduct($farmer, 'Heirloom Rice', 50.00);
        $tomatoes = $this->createProduct($farmer, 'Fresh Tomatoes', 40.00);
        $pendingCrop = $this->createProduct($farmer, 'Pending Crop', 30.00);
        $cancelledCrop = $this->createProduct($farmer, 'Cancelled Crop', 20.00);
        $otherCrop = $this->createProduct($otherFarmer, 'Other Farmer Corn', 100.00);

        $this->createSale($buyer, $farmer, [
            ['product' => $rice, 'quantity' => 10, 'unit_price' => 50.00],
        ], 'delivered', 60.00);

        $this->createSale($buyer, $farmer, [
            ['product' => $tomatoes, 'quantity' => 5, 'unit_price' => 40.00],
        ], 'picked_up', 0.00);

        $this->createSale($buyer, $farmer, [
            ['product' => $pendingCrop, 'quantity' => 2, 'unit_price' => 30.00],
        ], 'pending', 60.00);

        $this->createSale($buyer, $farmer, [
            ['product' => $cancelledCrop, 'quantity' => 3, 'unit_price' => 20.00],
        ], 'cancelled', 0.00);

        $this->createSale($buyer, $otherFarmer, [
            ['product' => $otherCrop, 'quantity' => 4, 'unit_price' => 100.00],
        ], 'delivered', 60.00);

        $response = $this->actingAs($farmer)->get(route('farmer.sales.index'));

        $response->assertOk();
        $response->assertSee('Sales History');
        $response->assertSee('Heirloom Rice');
        $response->assertSee('Fresh Tomatoes');
        $response->assertDontSee('Pending Crop');
        $response->assertDontSee('Cancelled Crop');
        $response->assertDontSee('Other Farmer Corn');

        $response->assertViewHas('totalProductSales', '700.00');
        $response->assertViewHas('completedTransactions', 2);
        $response->assertViewHas('productsSold', '15');
        $response->assertViewHas('shippingFees', '60.00');
        $response->assertViewHas('totalOrderValue', '760.00');
        $response->assertViewHas('todaySales', '700.00');
        $response->assertViewHas('monthSales', '700.00');
        $response->assertViewHas('sales', fn ($sales) => $sales->total() === 2);
    }

    public function test_sales_summary_sums_decimal_amounts_without_float_conversion(): void
    {
        $farmer = User::factory()->farmer()->create();
        $buyer = User::factory()->buyer()->create();
        $product = $this->createProduct($farmer, 'Fractional Harvest', 100.00);

        foreach ([
            ['quantity' => '0.001', 'amount' => '0.10'],
            ['quantity' => '0.002', 'amount' => '0.20'],
        ] as $line) {
            $order = Order::factory()->create([
                'buyer_id' => $buyer->id,
                'farmer_id' => $farmer->id,
                'total_amount' => $line['amount'],
                'shipping_fee' => '0.00',
                'delivery_method' => 'pickup',
                'status' => 'delivered',
                'completed_at' => now(),
            ]);
            OrderItem::factory()->create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => $line['quantity'],
                'unit_price' => '100.00',
                'subtotal' => $line['amount'],
            ]);
        }

        $response = $this->actingAs($farmer)->get(route('farmer.sales.index'));

        $response->assertOk()
            ->assertSee('₱0.30')
            ->assertViewHas('totalProductSales', '0.30')
            ->assertViewHas('totalOrderValue', '0.30')
            ->assertViewHas('productsSold', '0.003');
    }

    public function test_farmer_cannot_view_another_farmers_sale_details(): void
    {
        $farmer = User::factory()->farmer()->create();
        $otherFarmer = User::factory()->farmer()->create();
        $buyer = User::factory()->buyer()->create();
        $product = $this->createProduct($otherFarmer, 'Private Sale Crop', 25.00);

        $sale = $this->createSale($buyer, $otherFarmer, [
            ['product' => $product, 'quantity' => 2, 'unit_price' => 25.00],
        ], 'delivered', 0.00);

        $response = $this->actingAs($farmer)->get(route('farmer.sales.show', $sale));

        $response->assertForbidden();
    }

    public function test_sale_details_show_all_items_and_use_historical_prices(): void
    {
        $farmer = User::factory()->farmer()->create();
        $buyer = User::factory()->buyer()->create();
        $rice = $this->createProduct($farmer, 'Historical Rice', 50.00);
        $tomatoes = $this->createProduct($farmer, 'Historical Tomatoes', 40.00);

        $sale = $this->createSale($buyer, $farmer, [
            ['product' => $rice, 'quantity' => 10, 'unit_price' => 50.00],
            ['product' => $tomatoes, 'quantity' => 5, 'unit_price' => 40.00],
        ], 'delivered', 60.00);

        $rice->update(['price' => 999.00]);
        $tomatoes->update(['price' => 888.00]);

        $response = $this->actingAs($farmer)->get(route('farmer.sales.show', $sale));

        $response->assertOk();
        $response->assertSee('Sale Details');
        $response->assertSee($sale->order_number);
        $response->assertSee('Historical Rice');
        $response->assertSee('Historical Tomatoes');
        $response->assertSee($buyer->name);
        $response->assertSee($buyer->phone_number);
        $response->assertSee($sale->delivery_address);
        $response->assertSee('₱50.00');
        $response->assertSee('₱500.00');
        $response->assertSee('₱40.00');
        $response->assertSee('₱200.00');
        $response->assertSee('₱700.00');
        $response->assertSee('₱60.00');
        $response->assertSee('₱760.00');
        $response->assertDontSee('₱999.00');
        $response->assertDontSee('₱888.00');
    }

    public function test_sale_details_sum_stored_subtotals_for_fractional_quantities_in_cents(): void
    {
        $farmer = User::factory()->farmer()->create();
        $buyer = User::factory()->buyer()->create();
        $product = $this->createProduct($farmer, 'Fractional Sale Crop', 10.00);
        $sale = Order::factory()->create([
            'buyer_id' => $buyer->id,
            'farmer_id' => $farmer->id,
            'total_amount' => '0.18',
            'shipping_fee' => '0.00',
            'delivery_method' => 'pickup',
            'status' => 'delivered',
        ]);
        OrderItem::factory()->create([
            'order_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => '0.001',
            'unit_price' => '10.00',
            'subtotal' => '0.07',
        ]);
        OrderItem::factory()->create([
            'order_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => '0.002',
            'unit_price' => '10.00',
            'subtotal' => '0.11',
        ]);

        $response = $this->actingAs($farmer)->get(route('farmer.sales.show', $sale));

        $response->assertSee('Items subtotal')
            ->assertSee('₱0.18')
            ->assertDontSee('₱0.03');
    }

    public function test_search_and_date_filters_return_only_matching_transactions(): void
    {
        $farmer = User::factory()->farmer()->create();
        $buyer = User::factory()->buyer()->create();
        $rice = $this->createProduct($farmer, 'Heirloom Rice', 50.00);
        $tomatoes = $this->createProduct($farmer, 'Fresh Tomatoes', 40.00);

        $olderSale = $this->createSale($buyer, $farmer, [
            ['product' => $rice, 'quantity' => 2, 'unit_price' => 50.00],
        ], 'delivered', 0.00, Carbon::today()->subDays(5));

        $todaySale = $this->createSale($buyer, $farmer, [
            ['product' => $tomatoes, 'quantity' => 3, 'unit_price' => 40.00],
        ], 'delivered', 0.00, Carbon::today());

        $searchResponse = $this->actingAs($farmer)->get(route('farmer.sales.index', [
            'search' => 'Heirloom Rice',
        ]));

        $searchResponse->assertOk();
        $searchResponse->assertSee($olderSale->order_number);
        $searchResponse->assertSee('Heirloom Rice');
        $searchResponse->assertDontSee($todaySale->order_number);
        $searchResponse->assertDontSee('Fresh Tomatoes');

        $dateResponse = $this->actingAs($farmer)->get(route('farmer.sales.index', [
            'from' => Carbon::today()->toDateString(),
            'to' => Carbon::today()->toDateString(),
        ]));

        $dateResponse->assertOk();
        $dateResponse->assertSee($todaySale->order_number);
        $dateResponse->assertSee('Fresh Tomatoes');
        $dateResponse->assertDontSee($olderSale->order_number);
        $dateResponse->assertDontSee('Heirloom Rice');
    }

    public function test_cancelled_sales_can_be_filtered_but_are_excluded_from_completed_totals(): void
    {
        $farmer = User::factory()->farmer()->create();
        $buyer = User::factory()->buyer()->create();
        $completedCrop = $this->createProduct($farmer, 'Completed Squash', 50.00);
        $cancelledCrop = $this->createProduct($farmer, 'Cancelled Okra', 30.00);

        $completedSale = $this->createSale($buyer, $farmer, [
            ['product' => $completedCrop, 'quantity' => 10, 'unit_price' => 50.00],
        ], 'delivered', 0.00);

        $cancelledSale = $this->createSale($buyer, $farmer, [
            ['product' => $cancelledCrop, 'quantity' => 10, 'unit_price' => 30.00],
        ], 'cancelled', 0.00);

        $defaultResponse = $this->actingAs($farmer)->get(route('farmer.sales.index'));

        $defaultResponse->assertOk();
        $defaultResponse->assertSee($completedSale->order_number);
        $defaultResponse->assertDontSee($cancelledSale->order_number);
        $defaultResponse->assertDontSee('Cancelled Okra');
        $defaultResponse->assertViewHas('totalProductSales', '500.00');
        $defaultResponse->assertViewHas('completedTransactions', 1);

        $allResponse = $this->actingAs($farmer)->get(route('farmer.sales.index', [
            'status' => 'all',
        ]));

        $allResponse->assertOk();
        $allResponse->assertSee($cancelledSale->order_number);
        $allResponse->assertSee('Cancelled Okra');
        $allResponse->assertViewHas('totalProductSales', '500.00');

        $cancelledResponse = $this->actingAs($farmer)->get(route('farmer.sales.index', [
            'status' => 'cancelled',
        ]));

        $cancelledResponse->assertOk();
        $cancelledResponse->assertSee($cancelledSale->order_number);
        $cancelledResponse->assertSee('Cancelled Okra');
        $cancelledResponse->assertDontSee($completedSale->order_number);
        $cancelledResponse->assertDontSee('Completed Squash');
    }

    public function test_sales_history_paginates_transactions_fifteen_at_a_time(): void
    {
        $farmer = User::factory()->farmer()->create();
        $buyer = User::factory()->buyer()->create();
        $product = $this->createProduct($farmer, 'Pagination Crop', 10.00);

        for ($index = 0; $index < 16; $index++) {
            $this->createSale($buyer, $farmer, [
                ['product' => $product, 'quantity' => 1, 'unit_price' => 10.00],
            ], 'delivered', 0.00, now()->subMinutes($index));
        }

        $firstPage = $this->actingAs($farmer)->get(route('farmer.sales.index'));

        $firstPage->assertOk();
        $firstPage->assertViewHas('sales', fn ($sales) => $sales->perPage() === 15
            && $sales->count() === 15
            && $sales->total() === 16);

        $secondPage = $this->actingAs($farmer)->get(route('farmer.sales.index', [
            'page' => 2,
        ]));

        $secondPage->assertOk();
        $secondPage->assertViewHas('sales', fn ($sales) => $sales->count() === 1
            && $sales->total() === 16);
    }

    public function test_buyers_and_guests_cannot_access_farmer_sales_history(): void
    {
        $buyer = User::factory()->buyer()->create();

        $buyerResponse = $this->actingAs($buyer)->get(route('farmer.sales.index'));

        $buyerResponse->assertRedirect(route('marketplace'));
        $buyerResponse->assertSessionHas('error');

        auth()->logout();

        $guestResponse = $this->get(route('farmer.sales.index'));

        $guestResponse->assertRedirect(route('login'));
    }

    public function test_farmer_hub_completed_sales_includes_delivered_and_picked_up_orders(): void
    {
        $farmer = User::factory()->farmer()->create();
        $buyer = User::factory()->buyer()->create();
        $product = $this->createProduct($farmer, 'Farmer Hub Crop', 50.00);

        $this->createSale($buyer, $farmer, [
            ['product' => $product, 'quantity' => 10, 'unit_price' => 50.00],
        ], 'delivered', 60.00);

        $this->createSale($buyer, $farmer, [
            ['product' => $product, 'quantity' => 5, 'unit_price' => 40.00],
        ], 'picked_up', 0.00);

        $this->createSale($buyer, $farmer, [
            ['product' => $product, 'quantity' => 2, 'unit_price' => 25.00],
        ], 'pending', 0.00);

        $response = $this->actingAs($farmer)->get(route('farmer.marketplace'));

        $response->assertOk();
        $response->assertViewHas('farmerTotalSales', 760.0);
    }

    public function test_completed_sales_are_attributed_to_the_completion_month(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-15 12:00:00'));

        $farmer = User::factory()->farmer()->create();
        $buyer = User::factory()->buyer()->create();
        $product = $this->createProduct($farmer, 'October Harvest', 50.00);

        $this->createSale($buyer, $farmer, [
            ['product' => $product, 'quantity' => 10, 'unit_price' => 50.00],
        ], 'delivered', 0.00, Carbon::parse('2026-09-01 10:00:00'), Carbon::parse('2026-10-15 09:00:00'));

        $response = $this->actingAs($farmer)->get(route('farmer.sales.index'));

        $response->assertOk();
        $response->assertViewHas('todaySales', '500.00');
        $response->assertViewHas('monthSales', '500.00');
    }

    public function test_today_sales_use_the_completion_date_instead_of_the_order_creation_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-15 12:00:00'));

        $farmer = User::factory()->farmer()->create();
        $buyer = User::factory()->buyer()->create();
        $completedToday = $this->createProduct($farmer, 'Completed Today Crop', 10.00);
        $completedYesterday = $this->createProduct($farmer, 'Completed Yesterday Crop', 20.00);

        $this->createSale($buyer, $farmer, [
            ['product' => $completedToday, 'quantity' => 10, 'unit_price' => 10.00],
        ], 'delivered', 0.00, Carbon::parse('2026-10-14 09:00:00'), Carbon::parse('2026-10-15 08:00:00'));

        $this->createSale($buyer, $farmer, [
            ['product' => $completedYesterday, 'quantity' => 3, 'unit_price' => 20.00],
        ], 'delivered', 0.00, Carbon::parse('2026-10-15 09:00:00'), Carbon::parse('2026-10-14 08:00:00'));

        $response = $this->actingAs($farmer)->get(route('farmer.sales.index'));

        $response->assertOk();
        $response->assertViewHas('todaySales', '100.00');
        $response->assertViewHas('monthSales', '160.00');
    }

    public function test_date_filters_use_the_completion_date_with_a_creation_date_fallback(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-15 12:00:00'));

        $farmer = User::factory()->farmer()->create();
        $buyer = User::factory()->buyer()->create();
        $completedInOctober = $this->createProduct($farmer, 'Completed In October', 50.00);
        $legacySaleCrop = $this->createProduct($farmer, 'Legacy Completed Crop', 30.00);

        $octoberSale = $this->createSale($buyer, $farmer, [
            ['product' => $completedInOctober, 'quantity' => 2, 'unit_price' => 50.00],
        ], 'delivered', 0.00, Carbon::parse('2026-09-01 10:00:00'), Carbon::parse('2026-10-05 09:00:00'));

        $legacySale = $this->createSale($buyer, $farmer, [
            ['product' => $legacySaleCrop, 'quantity' => 1, 'unit_price' => 30.00],
        ], 'delivered', 0.00, Carbon::parse('2026-09-10 10:00:00'));

        $octoberResponse = $this->actingAs($farmer)->get(route('farmer.sales.index', [
            'from' => '2026-10-05',
            'to' => '2026-10-05',
        ]));

        $octoberResponse->assertOk();
        $octoberResponse->assertSee($octoberSale->order_number);
        $octoberResponse->assertDontSee($legacySale->order_number);

        $legacyResponse = $this->actingAs($farmer)->get(route('farmer.sales.index', [
            'from' => '2026-09-10',
            'to' => '2026-09-10',
        ]));

        $legacyResponse->assertOk();
        $legacyResponse->assertSee($legacySale->order_number);
        $legacyResponse->assertSee('Legacy: order placed');
        $legacyResponse->assertDontSee($octoberSale->order_number);
    }

    public function test_completion_period_metrics_remain_isolated_per_farmer(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-15 12:00:00'));

        $farmer = User::factory()->farmer()->create();
        $otherFarmer = User::factory()->farmer()->create();
        $buyer = User::factory()->buyer()->create();
        $ownProduct = $this->createProduct($farmer, 'Own October Crop', 50.00);
        $otherProduct = $this->createProduct($otherFarmer, 'Other October Crop', 100.00);

        $this->createSale($buyer, $farmer, [
            ['product' => $ownProduct, 'quantity' => 4, 'unit_price' => 50.00],
        ], 'delivered', 0.00, Carbon::parse('2026-10-15 08:00:00'), Carbon::parse('2026-10-15 09:00:00'));

        $this->createSale($buyer, $otherFarmer, [
            ['product' => $otherProduct, 'quantity' => 9, 'unit_price' => 100.00],
        ], 'delivered', 0.00, Carbon::parse('2026-10-15 08:00:00'), Carbon::parse('2026-10-15 09:00:00'));

        $response = $this->actingAs($farmer)->get(route('farmer.sales.index'));

        $response->assertOk();
        $response->assertViewHas('todaySales', '200.00');
        $response->assertViewHas('monthSales', '200.00');
        $response->assertViewHas('totalProductSales', '200.00');
        $response->assertViewHas('totalOrderValue', '200.00');
    }

    public function test_sales_history_sorts_by_completion_date_with_creation_date_fallback(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-15 12:00:00'));

        $farmer = User::factory()->farmer()->create();
        $buyer = User::factory()->buyer()->create();
        $product = $this->createProduct($farmer, 'Sortable Crop', 10.00);

        $olderCompletion = $this->createSale($buyer, $farmer, [
            ['product' => $product, 'quantity' => 1, 'unit_price' => 10.00],
        ], 'delivered', 0.00, Carbon::parse('2026-10-15 11:00:00'), Carbon::parse('2026-10-10 09:00:00'));

        $newerCompletion = $this->createSale($buyer, $farmer, [
            ['product' => $product, 'quantity' => 1, 'unit_price' => 10.00],
        ], 'delivered', 0.00, Carbon::parse('2026-10-01 09:00:00'), Carbon::parse('2026-10-12 09:00:00'));

        $legacySale = $this->createSale($buyer, $farmer, [
            ['product' => $product, 'quantity' => 1, 'unit_price' => 10.00],
        ], 'picked_up', 0.00, Carbon::parse('2026-10-14 10:00:00'));

        $response = $this->actingAs($farmer)->get(route('farmer.sales.index'));

        $response->assertOk();
        $response->assertViewHas('sales', fn ($sales) => $sales->pluck('id')->all() === [
            $legacySale->id,
            $newerCompletion->id,
            $olderCompletion->id,
        ]);
    }

    private function createProduct(User $farmer, string $name, float $price): Product
    {
        return Product::factory()->create([
            'user_id' => $farmer->id,
            'category_id' => Category::factory(),
            'name' => $name,
            'price' => $price,
            'quantity' => 100,
            'unit' => 'kg',
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<int, array{product: Product, quantity: int, unit_price: float}>  $lines
     */
    private function createSale(
        User $buyer,
        User $farmer,
        array $lines,
        string $status,
        float $shippingFee,
        ?Carbon $createdAt = null,
        ?Carbon $completedAt = null
    ): Order {
        $itemsSubtotal = collect($lines)->sum(
            fn (array $line): float => $line['unit_price'] * $line['quantity']
        );

        $order = Order::factory()->create([
            'buyer_id' => $buyer->id,
            'farmer_id' => $farmer->id,
            'order_type' => 'standard',
            'total_amount' => $itemsSubtotal + $shippingFee,
            'delivery_method' => $shippingFee > 0 ? 'deliver' : 'pickup',
            'delivery_address' => $shippingFee > 0 ? 'Test Delivery Street, Surigao City' : 'Pick up at Farm Location',
            'shipping_fee' => $shippingFee,
            'status' => $status,
            'created_at' => $createdAt ?? now(),
            'updated_at' => $createdAt ?? now(),
            'completed_at' => $completedAt,
        ]);

        foreach ($lines as $line) {
            OrderItem::factory()->create([
                'order_id' => $order->id,
                'product_id' => $line['product']->id,
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'subtotal' => $line['unit_price'] * $line['quantity'],
            ]);
        }

        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'amount' => $itemsSubtotal + $shippingFee,
        ]);

        if (in_array($status, Order::COMPLETED_STATUSES, true)) {
            $payment->update(['payment_status' => 'paid', 'payment_date' => now()]);
        }

        return $order;
    }
}
