@extends('layouts.app')

@section('title', 'Checkout & Order Finalization - FarmReach Cloud')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0 text-farm-primary"><i class="bi bi-shield-check text-success me-2"></i>Order Checkout</h2>
            <p class="text-muted small mb-0">Complete your delivery and payment preferences</p>
        </div>
        <a href="{{ route('cart.index') }}" class="btn btn-farm-outline btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to Cart
        </a>
    </div>

    @if($hasBulkItems)
        <div class="alert alert-warning d-flex align-items-center mb-4">
            <i class="bi bi-exclamation-octagon-fill fs-4 me-3"></i>
            <div>
                <strong>Wholesale / Bulk Order Detected:</strong> Your cart contains large volume agricultural products. Farmers are notified to prepare bulk packing directly.
            </div>
        </div>
    @endif

    <form id="checkoutForm" action="{{ route('checkout.place') }}" method="POST">
        @csrf

        <div class="row g-4">
            <!-- Delivery & Payment Configuration -->
            <div class="col-lg-7">
                <div class="farm-card p-4 mb-4">
                    <h5 class="fw-bold text-farm-primary mb-3 border-bottom pb-2">
                        <i class="bi bi-geo-alt me-1"></i> 1. Delivery & Fulfillment
                    </h5>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">Choose Fulfillment Method</label>
                        <div class="row g-3">
                            <div class="col-6">
                                <div class="p-3 border rounded-3 cursor-pointer delivery-option active" data-method="deliver" style="border-color: #1B4332 !important; background-color: #f7fdf9;">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="delivery_method" id="method_deliver" value="deliver" checked>
                                        <label class="form-check-label fw-bold" for="method_deliver">
                                            <i class="bi bi-truck me-1"></i> Direct Delivery
                                        </label>
                                    </div>
                                    <small class="text-muted d-block ps-4">Delivered to your doorstep (₱60.00)</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-3 border rounded-3 cursor-pointer delivery-option" data-method="pickup">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="delivery_method" id="method_pickup" value="pickup">
                                        <label class="form-check-label fw-bold" for="method_pickup">
                                            <i class="bi bi-shop me-1"></i> Farm Pick Up
                                        </label>
                                    </div>
                                    <small class="text-muted d-block ps-4">Pick up at farmer's farm stand (Free)</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3" id="deliveryAddressGroup">
                        <label for="delivery_address" class="form-label fw-bold small">Delivery Address</label>
                        <textarea class="form-control" id="delivery_address" name="delivery_address" rows="2" placeholder="Street name, Barangay, City, landmarks..." required>{{ old('delivery_address', $user->address) }}</textarea>
                    </div>

                    <div class="mb-3">
                        <label for="notes" class="form-label fw-bold small">Special Instructions / Notes (Optional)</label>
                        <input type="text" class="form-control" id="notes" name="notes" placeholder="e.g., Deliver before 2 PM, call upon arrival">
                    </div>
                </div>

                <!-- Payment Method Card (COD, Bank Transfer, GCash) -->
                <div class="farm-card p-4">
                    <h5 class="fw-bold text-farm-primary mb-3 border-bottom pb-2">
                        <i class="bi bi-credit-card me-1"></i> 2. Payment Method
                    </h5>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <div class="p-3 border rounded-3 text-center payment-option active" style="border-color: #1B4332 !important; background-color: #f7fdf9;">
                                <input class="form-check-input d-none" type="radio" name="payment_method" id="pay_cod" value="cod" checked>
                                <label for="pay_cod" class="d-block cursor-pointer">
                                    <i class="bi bi-cash-coin fs-2 text-success d-block mb-1"></i>
                                    <strong>Cash on Delivery</strong>
                                    <small class="text-muted d-block" style="font-size: 11px;">Pay when you receive</small>
                                </label>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="p-3 border rounded-3 text-center payment-option">
                                <input class="form-check-input d-none" type="radio" name="payment_method" id="pay_gcash" value="gcash">
                                <label for="pay_gcash" class="d-block cursor-pointer">
                                    <i class="bi bi-phone-fill fs-2 text-primary d-block mb-1"></i>
                                    <strong>GCash</strong>
                                    <small class="text-muted d-block" style="font-size: 11px;">Instant mobile e-wallet</small>
                                </label>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="p-3 border rounded-3 text-center payment-option">
                                <input class="form-check-input d-none" type="radio" name="payment_method" id="pay_bank" value="bank_transfer">
                                <label for="pay_bank" class="d-block cursor-pointer">
                                    <i class="bi bi-bank fs-2 text-warning d-block mb-1"></i>
                                    <strong>Bank Transfer</strong>
                                    <small class="text-muted d-block" style="font-size: 11px;">Online bank deposit</small>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3 d-none" id="referenceNumberGroup">
                        <label for="reference_number" class="form-label fw-bold small">Payment Reference Number (GCash / Bank)</label>
                        <input type="text" class="form-control" id="reference_number" name="reference_number" placeholder="e.g. GCASH-10928374 or BDO-TX-998">
                        <small class="text-muted">Enter the transaction reference number provided by your mobile banking or GCash app.</small>
                    </div>
                </div>
            </div>

            <!-- Order Items Review & Final CTA -->
            <div class="col-lg-5">
                <div class="farm-card p-4 mb-4">
                    <h5 class="fw-bold text-farm-primary mb-3 border-bottom pb-2">Order Items ({{ count($cart) }})</h5>

                    <div class="cart-items-preview mb-3" style="max-height: 260px; overflow-y: auto;">
                        @foreach($cart as $item)
                            <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                <div class="d-flex align-items-center gap-2">
                                    <img src="{{ $item['image_path'] }}" class="rounded-2" alt="{{ $item['name'] }}" style="width: 40px; height: 40px; object-fit: cover;">
                                    <div>
                                        <h6 class="mb-0 small fw-bold text-dark">{{ $item['name'] }}</h6>
                                        <small class="text-muted">x{{ $item['quantity'] }} &bull; {{ $item['farmer_name'] }}</small>
                                    </div>
                                </div>
                                <span class="fw-bold text-farm-primary small">
                                    ₱{{ number_format($item['price'] * $item['quantity'], 2) }}
                                </span>
                            </div>
                        @endforeach
                    </div>

                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted small">Items Subtotal:</span>
                        <span class="fw-semibold">₱{{ number_format($subtotal, 2) }}</span>
                    </div>

                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted small">Shipping / Handling:</span>
                        <span class="fw-semibold" id="shippingFeeDisplay">₱60.00</span>
                    </div>

                    <hr class="my-2">

                    <div class="d-flex justify-content-between align-items-baseline mb-4">
                        <strong class="fs-5 text-dark">Total Amount:</strong>
                        <strong class="fs-4 text-farm-primary" id="totalOrderAmount">
                            ₱{{ number_format($subtotal + 60, 2) }}
                        </strong>
                    </div>

                    <!-- Button that opens Order Review Modal (per wireframe page 33) -->
                    <button type="button" class="btn btn-farm-primary w-100 py-2 fw-bold shadow-sm" id="btnReviewOrder">
                        <i class="bi bi-file-earmark-check me-1"></i> Review & Confirm Order
                    </button>
                </div>
            </div>
        </div>

        <!-- Buyer OrderReview(CheckoutToDeliver) Modal (Exact Wireframe match page 33) -->
        <div class="modal fade" id="orderReviewModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content farm-card border-0">
                    <div class="modal-header modal-header-farm">
                        <h5 class="modal-title fw-bold">Order Review</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="p-3 bg-light rounded-3 mb-3 border">
                            <div class="row g-2 small">
                                <div class="col-5 text-muted">Buyer Name:</div>
                                <div class="col-7 fw-bold text-dark">{{ $user->name }}</div>

                                <div class="col-5 text-muted">Delivery Method:</div>
                                <div class="col-7 fw-bold text-dark" id="modalReviewDeliveryMethod">Direct Delivery</div>

                                <div class="col-5 text-muted">Delivery Address:</div>
                                <div class="col-7 text-dark" id="modalReviewAddress">{{ $user->address }}</div>

                                <div class="col-5 text-muted">Total Quantity:</div>
                                <div class="col-7 fw-bold text-dark">{{ array_sum(array_column($cart, 'quantity')) }} items ({{ number_format($totalKg, 1) }} kg)</div>

                                <div class="col-5 text-muted">Payment Method:</div>
                                <div class="col-7 fw-bold text-dark" id="modalReviewPaymentMethod">Cash on Delivery (COD)</div>

                                <div class="col-5 text-muted">Shipping Fee:</div>
                                <div class="col-7 text-dark" id="modalReviewShipping">₱60.00</div>

                                <div class="col-5 text-muted">Total Amount:</div>
                                <div class="col-7 fw-bold text-success fs-5" id="modalReviewTotal">₱{{ number_format($subtotal + 60, 2) }}</div>
                            </div>
                        </div>

                        <p class="text-muted small mb-0">
                            <i class="bi bi-info-circle"></i> Once placed, each farmer receives notification of their assigned portion to harvest and prepare.
                        </p>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Edit Order</button>
                        <button type="button" class="btn btn-farm-accent text-dark fw-bold px-4" id="btnConfirmPlaceOrder">
                            Place Order Now
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

@push('scripts')
<script>
    $(document).ready(function () {
        const subtotal = {{ $subtotal }};

        // Fulfillment radio toggle
        $('input[name="delivery_method"]').on('change', function () {
            const method = $(this).val();
            $('.delivery-option').removeClass('active').css({'border-color': '#E2E8F0', 'background-color': '#ffffff'});
            $(this).closest('.delivery-option').addClass('active').css({'border-color': '#1B4332', 'background-color': '#f7fdf9'});

            if (method === 'deliver') {
                $('#deliveryAddressGroup').show();
                $('#delivery_address').prop('required', true);
                $('#shippingFeeDisplay').text('₱60.00');
                $('#totalOrderAmount').text('₱' + (subtotal + 60).toFixed(2));
            } else {
                $('#deliveryAddressGroup').hide();
                $('#delivery_address').prop('required', false);
                $('#shippingFeeDisplay').text('₱0.00');
                $('#totalOrderAmount').text('₱' + subtotal.toFixed(2));
            }
        });

        // Payment radio toggle
        $('.payment-option').on('click', function () {
            $('.payment-option').removeClass('active').css({'border-color': '#E2E8F0', 'background-color': '#ffffff'});
            $(this).addClass('active').css({'border-color': '#1B4332', 'background-color': '#f7fdf9'});
            const $radio = $(this).find('input[type="radio"]');
            $radio.prop('checked', true);

            if ($radio.val() === 'gcash' || $radio.val() === 'bank_transfer') {
                $('#referenceNumberGroup').removeClass('d-none');
            } else {
                $('#referenceNumberGroup').addClass('d-none');
            }
        });

        // Review Modal
        $('#btnReviewOrder').on('click', function () {
            const method = $('input[name="delivery_method"]:checked').val();
            const address = $('#delivery_address').val();
            const payment = $('input[name="payment_method"]:checked').val();
            const shipping = method === 'deliver' ? 60 : 0;
            const total = subtotal + shipping;

            $('#modalReviewDeliveryMethod').text(method === 'deliver' ? 'Direct Delivery' : 'Farm Stand Pick Up');
            $('#modalReviewAddress').text(method === 'deliver' ? (address || 'Not specified') : 'Pick up at registered farm location');
            $('#modalReviewPaymentMethod').text(payment.toUpperCase());
            $('#modalReviewShipping').text('₱' + shipping.toFixed(2));
            $('#modalReviewTotal').text('₱' + total.toFixed(2));

            const modal = new bootstrap.Modal(document.getElementById('orderReviewModal'));
            modal.show();
        });

        $('#btnConfirmPlaceOrder').on('click', function () {
            $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Submitting...');
            $('#checkoutForm').submit();
        });
    });
</script>
@endpush
@endsection
