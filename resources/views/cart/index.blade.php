@extends('layouts.app')

@section('title', 'Shopping Cart - FarmReach Cloud')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0 text-farm-primary"><i class="bi bi-cart3 text-warning me-2"></i>Your Shopping Cart</h2>
            <p class="text-muted small mb-0">Review your direct farm produce before proceeding to checkout</p>
        </div>
        <a href="{{ route('marketplace') }}" class="btn btn-farm-outline btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Continue Shopping
        </a>
    </div>

    @if(empty($cart))
        <div class="farm-card text-center py-5">
            <div class="rounded-circle bg-light p-4 d-inline-flex mb-3">
                <i class="bi bi-basket3 fs-1 text-muted"></i>
            </div>
            <h4 class="fw-bold mb-2">Your cart is empty</h4>
            <p class="text-muted mb-4">Explore fresh agricultural crops and farm products available in your community.</p>
            <div class="d-flex justify-content-center gap-3">
                <a href="{{ route('marketplace') }}" class="btn btn-farm-primary px-4 py-2">
                    <i class="bi bi-shop me-1"></i> Explore Marketplace
                </a>
                <a href="{{ route('recipes.index') }}" class="btn btn-farm-outline px-4 py-2">
                    <i class="bi bi-book me-1"></i> Shop by Recipe
                </a>
            </div>
        </div>
    @else
        <div class="row g-4">
            <!-- Cart Items List -->
            <div class="col-lg-8">
                <div class="farm-card p-3">
                    <div class="table-responsive">
                        <table class="table align-middle farm-table mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 44px;" class="text-center">
                                        <input type="checkbox" id="selectAllCart" class="form-check-input" checked title="Select All Items">
                                    </th>
                                    <th>Product</th>
                                    <th>Farmer</th>
                                    <th>Unit Price</th>
                                    <th>Quantity</th>
                                    <th>Subtotal</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($cart as $id => $item)
                                    <tr class="cart-item-row" data-id="{{ $id }}">
                                        <td class="text-center">
                                            <input type="checkbox" 
                                                   name="selected_items[]" 
                                                   form="checkoutForm"
                                                   value="{{ $id }}" 
                                                   class="form-check-input cart-item-check" 
                                                   data-price="{{ $item['price'] }}" 
                                                   data-kg="{{ $item['kilograms'] ?? 0 }}"
                                                   checked>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-3">
                                                <img src="{{ $item['image_path'] }}" class="rounded-3 shadow-sm" alt="{{ $item['name'] }}" style="width: 54px; height: 54px; object-fit: cover;">
                                                <div>
                                                    <h6 class="fw-bold mb-0 text-dark">{{ $item['name'] }}</h6>
                                                    <small class="text-muted">
                                                        Unit: {{ $item['unit'] }}
                                                        @if(!empty($item['weight_per_unit']))
                                                            ({{ number_format($item['weight_per_unit'], 2) }} kg/unit)
                                                        @endif
                                                    </small>
                                                    @if($item['quantity'] >= 20 || ($item['order_type'] ?? '') === 'bulk')
                                                        <span class="badge bg-warning text-dark ms-1" style="font-size: 10px;">Bulk Wholesale</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="small fw-semibold">{{ $item['farmer_name'] }}</div>
                                            <div class="text-muted" style="font-size: 11px;">
                                                <i class="bi bi-geo-alt"></i> {{ $item['farmer_address'] }}
                                            </div>
                                        </td>
                                        <td class="fw-semibold">
                                            ₱{{ number_format($item['price'], 2) }}
                                            <span class="text-muted small fw-normal">/ {{ $item['price_unit_label'] ?? $item['unit'] }}</span>
                                        </td>
                                        <td>
                                            <div class="qty-control d-flex align-items-center">
                                                <button type="button" class="qty-btn btn-qty-minus">-</button>
                                                <input type="number" 
                                                       class="qty-input cart-qty-input" 
                                                       data-product-id="{{ $id }}" 
                                                       value="{{ $item['quantity'] }}" 
                                                       min="1" 
                                                       max="999">
                                                <button type="button" class="qty-btn btn-qty-plus">+</button>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="fw-bold text-farm-primary item-subtotal">
                                                ₱{{ number_format($item['price'] * $item['quantity'], 2) }}
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <form action="{{ route('cart.remove') }}" method="POST">
                                                @csrf
                                                <input type="hidden" name="product_id" value="{{ $id }}">
                                                <button type="submit" class="btn btn-sm btn-link text-danger p-0" title="Remove item">
                                                    <i class="bi bi-trash3-fill"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Order Summary Card -->
            <div class="col-lg-4">
                <div class="farm-card p-4">
                    <h5 class="fw-bold mb-3 text-farm-primary border-bottom pb-2">Order Summary</h5>

                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Selected Produce:</span>
                        <strong id="selectedItemsText">{{ count($cart) }} item(s)</strong>
                    </div>

                    <div class="d-flex justify-content-between mb-2" id="weightSummaryRow" @if($totalKilograms <= 0) style="display: none;" @endif>
                        <span class="text-muted">Total Produce Weight:</span>
                        <strong id="selectedWeightText">{{ number_format($totalKilograms, 1) }} kg</strong>
                    </div>

                    <div class="d-flex justify-content-between mb-3">
                        <span class="text-muted">Subtotal:</span>
                        <strong class="fs-5 text-farm-primary cart-grand-total" id="selectedSubtotalText">₱{{ number_format($subtotal, 2) }}</strong>
                    </div>

                    <div class="alert alert-light border small text-muted mb-4">
                        <i class="bi bi-info-circle text-primary me-1"></i> Delivery fees and fulfillment options (Deliver or Pick-up) will be configured on the next checkout step.
                    </div>

                    @auth
                        <form action="{{ route('checkout') }}" method="GET" id="checkoutForm">
                            <button type="submit" class="btn btn-farm-primary w-100 py-2 fw-bold shadow-sm" id="btnProceedCheckout">
                                Proceed to Checkout (<span id="btnCheckoutCount">{{ count($cart) }}</span>) &rarr;
                            </button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="btn btn-farm-primary w-100 py-2 fw-bold shadow-sm">
                            Sign In to Checkout &rarr;
                        </a>
                        <small class="text-muted text-center d-block mt-2">You can sign in using email or phone number.</small>
                    @endauth
                </div>
            </div>
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
    $(document).ready(function () {
        function recalculateCartSelection() {
            let totalSubtotal = 0;
            let totalWeight = 0;
            let selectedCount = 0;
            const totalItems = $('.cart-item-check').length;

            $('.cart-item-check').each(function () {
                if ($(this).is(':checked')) {
                    selectedCount++;
                    const $row = $(this).closest('.cart-item-row');
                    const price = parseFloat($(this).data('price')) || 0;
                    const qty = parseInt($row.find('.cart-qty-input').val()) || 1;
                    const kg = parseFloat($(this).data('kg')) || 0;

                    totalSubtotal += price * qty;
                    totalWeight += kg * qty;
                }
            });

            $('#selectedItemsText').text(selectedCount + ' item(s)');
            $('#selectedSubtotalText').text('₱' + totalSubtotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
            $('#selectedWeightText').text(totalWeight.toFixed(1) + ' kg');
            $('#btnCheckoutCount').text(selectedCount);

            if (totalWeight > 0) {
                $('#weightSummaryRow').show();
            } else {
                $('#weightSummaryRow').hide();
            }

            if (selectedCount === 0) {
                $('#btnProceedCheckout').prop('disabled', true).addClass('opacity-50');
            } else {
                $('#btnProceedCheckout').prop('disabled', false).removeClass('opacity-50');
            }

            $('#selectAllCart').prop('checked', totalItems > 0 && selectedCount === totalItems);
        }

        $('#selectAllCart').on('change', function () {
            const isChecked = $(this).is(':checked');
            $('.cart-item-check').prop('checked', isChecked);
            recalculateCartSelection();
        });

        $(document).on('change', '.cart-item-check', function () {
            recalculateCartSelection();
        });

        $(document).on('change', '.cart-qty-input', function () {
            setTimeout(recalculateCartSelection, 150);
        });

        $('#checkoutForm').on('submit', function (e) {
            const checkedCount = $('.cart-item-check:checked').length;
            if (checkedCount === 0) {
                e.preventDefault();
                alert('Please select at least one produce item to proceed to checkout.');
            }
        });

        recalculateCartSelection();
    });
</script>
@endpush
