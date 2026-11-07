<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\User;
use App\Support\Quantity;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FractionalQuantityFeatureTest extends TestCase
{
    public static function kilogramQuantities(): array
    {
        return [
            'quarter kilogram' => ['0.25'],
            'half kilogram' => ['0.5'],
            'one and a half kilograms' => ['1.5'],
        ];
    }

    public static function kilogramDisplayCases(): array
    {
        return [
            'quarter kilogram' => ['0.25', '0.25'],
            'one gram' => ['0.001', '0.001'],
            'whole kilograms' => ['2', '2'],
        ];
    }

    #[DataProvider('kilogramQuantities')]
    public function test_buyer_can_add_fractional_kilogram_quantities_to_the_cart(string $quantity): void
    {
        $farmer = User::factory()->farmer()->create();
        $buyer = User::factory()->buyer()->create();
        $product = $this->createProduct($farmer);

        $this->actingAs($buyer)
            ->post(route('cart.add'), [
                'product_id' => $product->id,
                'quantity' => $quantity,
            ])
            ->assertSessionHas('cart');

        $this->assertSame($quantity, session("cart.{$product->id}.quantity"));
    }

    public function test_farmer_can_save_fractional_kg_stock_and_non_weight_stock_remains_whole(): void
    {
        $farmer = User::factory()->farmer()->create();
        $category = Category::factory()->create();

        $this->actingAs($farmer)
            ->post(route('farmer.products.store'), [
                'name' => 'Fractional Squash',
                'category_id' => $category->id,
                'price' => '50.00',
                'quantity' => '10.125',
                'unit' => 'kg',
            ])
            ->assertSessionHas('success');

        $product = Product::where('name', 'Fractional Squash')->firstOrFail();
        $this->assertSame('10.125', $product->quantity);
        $this->assertSame('10.125 kg', $product->stock_with_unit);

        $this->actingAs($farmer)
            ->put(route('farmer.products.update', $product), [
                'name' => $product->name,
                'category_id' => $category->id,
                'price' => '50.00',
                'quantity' => '9.875',
                'unit' => 'kg',
            ])
            ->assertSessionHas('success');

        $this->assertSame('9.875', $product->fresh()->quantity);

        $this->actingAs($farmer)
            ->post(route('farmer.products.store'), [
                'name' => 'Fractional Pieces',
                'category_id' => $category->id,
                'price' => '20.00',
                'quantity' => '2.5',
                'unit' => 'pcs',
            ])
            ->assertSessionHasErrors('quantity');

        $this->assertDatabaseMissing('products', ['name' => 'Fractional Pieces']);
    }

    public function test_cart_rejects_non_weight_fractions_excess_precision_and_stock_overruns(): void
    {
        $farmer = User::factory()->farmer()->create();
        $buyer = User::factory()->buyer()->create();
        $pieces = $this->createProduct($farmer, ['unit' => 'pcs']);
        $kilograms = $this->createProduct($farmer, ['quantity' => '0.5']);

        $this->actingAs($buyer)
            ->post(route('cart.add'), [
                'product_id' => $pieces->id,
                'quantity' => '0.5',
            ])
            ->assertSessionHasErrors('quantity');

        $this->post(route('cart.add'), [
            'product_id' => $kilograms->id,
            'quantity' => '0.1251',
        ])->assertSessionHasErrors('quantity');

        $this->post(route('cart.add'), [
            'product_id' => $kilograms->id,
            'quantity' => '0.75',
        ])->assertSessionHas('error');

        $this->post(route('cart.add'), [
            'product_id' => $kilograms->id,
            'quantity' => '0',
        ])->assertSessionHasErrors('quantity');

        $this->post(route('cart.add'), [
            'product_id' => $kilograms->id,
            'quantity' => '-0.5',
        ])->assertSessionHasErrors('quantity');

        $this->assertSame(0, count(session('cart', [])));
        $this->assertSame('0.500', $kilograms->fresh()->quantity);
    }

    public function test_fractional_precision_is_not_rounded_when_quantity_is_a_float(): void
    {
        $farmer = User::factory()->farmer()->create();
        $buyer = User::factory()->buyer()->create();
        $product = $this->createProduct($farmer);

        $this->assertTrue(Quantity::isValid(0.001));
        $this->assertFalse(Quantity::isValid(0.0009));

        $this->actingAs($buyer)
            ->postJson(route('cart.add'), [
                'product_id' => $product->id,
                'quantity' => 0.0009,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity');
    }

    public function test_cart_refresh_and_quantity_updates_preserve_fractional_values_and_exact_subtotals(): void
    {
        $farmer = User::factory()->farmer()->create();
        $buyer = User::factory()->buyer()->create();
        $product = $this->createProduct($farmer, ['quantity' => '2']);

        $cart = [$product->id => $product->cartItemPayload('0.25')];

        $this->actingAs($buyer)
            ->withSession(['cart' => $cart])
            ->get(route('cart.index'))
            ->assertOk()
            ->assertSee('value="0.25"', false)
            ->assertSee('₱12.50');

        $this->postJson(route('cart.update'), [
            'product_id' => $product->id,
            'quantity' => '1.5',
        ])
            ->assertOk()
            ->assertJsonPath('item_subtotal', '75.00')
            ->assertJsonPath('cart_total', '75.00');

        $this->assertSame('1.5', session("cart.{$product->id}.quantity"));
        $this->assertSame('5000', (string) session("cart.{$product->id}.price_cents"));
    }

    #[DataProvider('kilogramDisplayCases')]
    public function test_cart_weight_summary_preserves_fractional_and_whole_kilograms(
        string $quantity,
        string $expectedWeight
    ): void {
        $farmer = User::factory()->farmer()->create();
        $buyer = User::factory()->buyer()->create();
        $product = $this->createProduct($farmer);
        $cart = [$product->id => $product->cartItemPayload($quantity)];

        $response = $this->actingAs($buyer)
            ->withSession(['cart' => $cart])
            ->get(route('cart.index'));

        $html = $response->getContent();
        $appScriptPosition = strpos($html, 'src="/js/farmreach-app.js?v=2.1"');
        $cartHelperPosition = strpos($html, 'window.FarmReachQuantity.fromMilliUnits');

        $response->assertSee('id="selectedWeightText">'.$expectedWeight.' kg</strong>', false)
            ->assertSee('window.FarmReachQuantity.fromMilliUnits', false)
            ->assertDontSee('quantityTools.fromMilliUnits', false);
        $this->assertNotFalse($appScriptPosition);
        $this->assertNotFalse($cartHelperPosition);
        $this->assertLessThan($cartHelperPosition, $appScriptPosition);
    }

    public function test_farmer_order_weight_summaries_preserve_fractional_and_whole_kilograms(): void
    {
        $farmer = User::factory()->farmer()->create();
        $buyer = User::factory()->buyer()->create();
        $order = Order::factory()->create([
            'buyer_id' => $buyer->id,
            'farmer_id' => $farmer->id,
        ]);

        foreach ([
            ['quantity' => '0.25', 'expected' => '(0.25 kg)'],
            ['quantity' => '0.001', 'expected' => '(0.001 kg)'],
            ['quantity' => '2', 'expected' => '(2 kg)'],
        ] as $case) {
            $product = $this->createProduct($farmer);
            OrderItem::factory()->create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => $case['quantity'],
            ]);
        }

        $response = $this->actingAs($farmer)->get(route('farmer.orders'));

        $response->assertSee('(0.25 kg)')
            ->assertSee('(0.001 kg)')
            ->assertSee('(2 kg)');
    }

    public function test_checkout_records_half_kilogram_price_stock_and_restocks_once_on_cancellation(): void
    {
        $farmer = User::factory()->farmer()->create();
        $buyer = User::factory()->buyer()->create();
        $product = $this->createProduct($farmer);
        $cart = [$product->id => $product->cartItemPayload('0.5')];

        $this->actingAs($buyer)
            ->withSession(['cart' => $cart])
            ->post(route('checkout.place'), [
                'delivery_method' => 'deliver',
                'delivery_address' => 'Buyer delivery address',
                'payment_method' => 'cod',
                'selected_items' => [$product->id],
            ])
            ->assertRedirect(route('buyer.orders'))
            ->assertSessionHas('success');

        $order = Order::where('buyer_id', $buyer->id)->firstOrFail();
        $item = $order->items()->firstOrFail();
        $this->assertSame('0.500', $item->quantity);
        $this->assertSame('25.00', $item->subtotal);
        $this->assertSame('25.00', $order->total_amount);
        $this->assertSame('25.00', $order->payment->amount);
        $this->assertSame('9.500', $product->fresh()->quantity);

        $this->actingAs($buyer)
            ->post(route('buyer.orders.cancel', $order))
            ->assertSessionHas('success');

        $this->assertSame('10.000', $product->fresh()->quantity);

        $this->post(route('buyer.orders.cancel', $order))
            ->assertSessionHas('error');

        $this->assertSame('10.000', $product->fresh()->quantity);
    }

    public function test_farmer_cancellation_restores_fractional_stock_once(): void
    {
        $farmer = User::factory()->farmer()->create();
        $buyer = User::factory()->buyer()->create();
        $product = $this->createProduct($farmer, ['quantity' => '9.5']);
        $order = Order::factory()->create([
            'buyer_id' => $buyer->id,
            'farmer_id' => $farmer->id,
            'total_amount' => '25.00',
            'shipping_fee' => '0.00',
            'delivery_method' => 'deliver',
            'status' => 'confirmed',
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => '0.5',
            'unit_price' => '50.00',
            'subtotal' => '25.00',
        ]);
        Payment::factory()->create([
            'order_id' => $order->id,
            'amount' => '25.00',
        ]);

        $this->actingAs($farmer)
            ->post(route('farmer.orders.status', $order), ['status' => 'cancelled'])
            ->assertSessionHas('success');

        $this->assertSame('10.000', $product->fresh()->quantity);

        $this->post(route('farmer.orders.status', $order), ['status' => 'cancelled'])
            ->assertSessionHas('error');

        $this->assertSame('10.000', $product->fresh()->quantity);
    }

    public function test_recipe_batch_add_preserves_fractional_kg_quantities(): void
    {
        $farmer = User::factory()->farmer()->create();
        $buyer = User::factory()->buyer()->create();
        $product = $this->createProduct($farmer);

        $this->actingAs($buyer)
            ->post(route('recipes.add-batch'), [
                'products' => [
                    $product->id => [
                        'id' => $product->id,
                        'quantity' => '0.25',
                    ],
                ],
            ])
            ->assertRedirect(route('cart.index'))
            ->assertSessionHas('success');

        $this->assertSame('0.25', session("cart.{$product->id}.quantity"));
    }

    public function test_fractional_quantities_are_displayed_in_marketplace_sales_and_api_outputs(): void
    {
        $farmer = User::factory()->farmer()->create();
        $buyer = User::factory()->buyer()->create();
        $product = $this->createProduct($farmer, [
            'name' => 'Fractional Squash',
            'quantity' => '0.25',
        ]);
        $recipe = Recipe::factory()->create();
        RecipeIngredient::factory()->create([
            'recipe_id' => $recipe->id,
            'category_id' => $product->category_id,
            'name' => 'Fractional Squash',
        ]);

        $this->get(route('marketplace.show', $product))
            ->assertOk()
            ->assertSee('Stock: 0.25 kg');

        $this->actingAs($farmer)
            ->get(route('farmer.products'))
            ->assertSee('0.25 kg')
            ->assertDontSee('0.250 kg');

        $this->get(route('recipes.show', $recipe))
            ->assertSee('0.25 left')
            ->assertDontSee('0.250 left');

        $this->getJson('/api/products/'.$product->id)
            ->assertOk()
            ->assertJsonPath('data.quantity', 0.25);

        $order = Order::factory()->create([
            'buyer_id' => $buyer->id,
            'farmer_id' => $farmer->id,
            'total_amount' => '25.00',
            'shipping_fee' => '0.00',
            'delivery_method' => 'pickup',
            'status' => 'delivered',
            'completed_at' => now(),
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => '0.5',
            'unit_price' => '50.00',
            'subtotal' => '25.00',
        ]);

        Sanctum::actingAs($buyer);
        $this->getJson('/api/orders')
            ->assertOk()
            ->assertJsonPath('data.0.items.0.quantity', 0.5);

        $this->actingAs($farmer)
            ->get(route('farmer.sales.index'))
            ->assertOk()
            ->assertSee('0.5 kg');
    }

    public function test_buyer_order_summary_counts_items_instead_of_combining_different_units(): void
    {
        $farmer = User::factory()->farmer()->create();
        $buyer = User::factory()->buyer()->create();
        $kilograms = $this->createProduct($farmer, ['name' => 'Weighted Produce', 'unit' => 'kg']);
        $pieces = $this->createProduct($farmer, ['name' => 'Whole Produce', 'unit' => 'pcs']);
        $order = Order::factory()->create([
            'buyer_id' => $buyer->id,
            'farmer_id' => $farmer->id,
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $kilograms->id,
            'quantity' => '2',
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $pieces->id,
            'quantity' => '3',
        ]);

        $response = $this->actingAs($buyer)->get(route('buyer.orders', ['view' => 'table']));

        $response->assertSee('Items')
            ->assertSee('2 items')
            ->assertDontSee('<td class="small">5</td>', false);
    }

    public function test_fractional_data_prevents_a_destructive_quantity_migration_rollback(): void
    {
        $farmer = User::factory()->farmer()->create();
        $product = $this->createProduct($farmer, ['quantity' => '9.5']);
        $migration = require database_path(
            'migrations/2026_11_07_072813_alter_product_and_order_item_quantities_to_decimal.php'
        );

        try {
            $migration->down();
            $this->fail('The migration rollback should refuse to discard fractional product stock.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('fractional quantities in products', $exception->getMessage());
        }

        $this->assertSame('9.500', $product->fresh()->quantity);

        $product->update(['quantity' => '10']);
        $order = Order::factory()->create([
            'buyer_id' => User::factory()->buyer(),
            'farmer_id' => $farmer->id,
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => '0.125',
        ]);

        try {
            $migration->down();
            $this->fail('The migration rollback should refuse to discard fractional order quantities.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('fractional quantities in order_items', $exception->getMessage());
        }

        $this->assertSame('0.125', OrderItem::where('order_id', $order->id)->value('quantity'));
        $this->assertTrue(DB::table('products')->where('id', $product->id)->exists());
    }

    public static function signedIntegerOverflowCases(): array
    {
        return [
            'product below signed int minimum' => ['products', '-2147483649'],
            'product above signed int maximum' => ['products', '2147483648'],
            'order item below signed int minimum' => ['order_items', '-2147483649'],
            'order item above signed int maximum' => ['order_items', '2147483648'],
        ];
    }

    #[DataProvider('signedIntegerOverflowCases')]
    public function test_migration_rollback_refuses_values_outside_the_signed_integer_range(
        string $table,
        string $quantity
    ): void {
        $farmer = User::factory()->farmer()->create();
        $product = $this->createProduct($farmer);

        if ($table === 'products') {
            DB::table('products')->where('id', $product->id)->update(['quantity' => $quantity]);
        } else {
            $order = Order::factory()->create([
                'buyer_id' => User::factory()->buyer(),
                'farmer_id' => $farmer->id,
            ]);
            OrderItem::factory()->create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => $quantity,
            ]);
        }

        $migration = require database_path(
            'migrations/2026_11_07_072813_alter_product_and_order_item_quantities_to_decimal.php'
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("outside the original signed integer range in {$table}");

        $migration->down();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createProduct(User $farmer, array $attributes = []): Product
    {
        return Product::factory()->create(array_merge([
            'user_id' => $farmer->id,
            'category_id' => Category::factory(),
            'price' => '50.00',
            'quantity' => '10',
            'unit' => 'kg',
            'is_active' => true,
        ], $attributes));
    }
}
