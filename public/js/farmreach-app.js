/* FarmReach Cloud - jQuery Interactive Scripting */
$(document).ready(function () {
    // CSRF Token Setup for all jQuery AJAX requests
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    // 1. Quantity buttons in Cart / Product detail
    $(document).on('click', '.btn-qty-plus', function () {
        const $input = $(this).closest('.qty-control').find('.qty-input');
        const max = parseInt($input.attr('max')) || 9999;
        let val = parseInt($input.val()) || 1;
        if (val < max) {
            $input.val(val + 1).trigger('change');
        }
    });

    $(document).on('click', '.btn-qty-minus', function () {
        const $input = $(this).closest('.qty-control').find('.qty-input');
        const min = parseInt($input.attr('min')) || 1;
        let val = parseInt($input.val()) || 1;
        if (val > min) {
            $input.val(val - 1).trigger('change');
        }
    });

    // 2. Cart Quantity Update via AJAX
    $(document).on('change', '.cart-qty-input', function () {
        const productId = $(this).data('product-id');
        const quantity = $(this).val();
        const $row = $(this).closest('.cart-item-row');

        $.ajax({
            url: '/cart/update',
            method: 'POST',
            data: { product_id: productId, quantity: quantity },
            success: function (res) {
                if (res.success) {
                    $row.find('.item-subtotal').text('₱' + res.item_subtotal);
                    $('.cart-grand-total').text('₱' + res.cart_total);
                    $('.cart-badge-count').text(res.cart_count);
                    showToast('Cart Updated', 'Quantity updated successfully.');
                }
            },
            error: function (xhr) {
                const msg = xhr.responseJSON ? xhr.responseJSON.message : 'Error updating cart.';
                alert(msg);
                location.reload();
            }
        });
    });

    // 3. Add to Cart via AJAX with Confirmation Modal (matching wireframe Buyer AddedOrder)
    $(document).on('submit', '.form-add-to-cart', function (e) {
        e.preventDefault();
        const $form = $(this);
        const $btn = $form.find('button[type="submit"]');
        const originalText = $btn.html();

        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Adding...');

        $.ajax({
            url: $form.attr('action'),
            method: 'POST',
            data: $form.serialize(),
            success: function (res) {
                $btn.prop('disabled', false).html(originalText);
                if (res.success) {
                    $('.cart-badge-count').text(res.cart_count).show();
                    $('#addedProductName').text(res.product_name);
                    $('#addedProductQty').text(res.quantity);
                    const addedModal = new bootstrap.Modal(document.getElementById('addedToCartModal'));
                    addedModal.show();
                }
            },
            error: function (xhr) {
                $btn.prop('disabled', false).html(originalText);
                const msg = xhr.responseJSON ? xhr.responseJSON.message : 'Could not add to cart.';
                alert(msg);
            }
        });
    });

    // 4. Recipe Ingredient Checkbox selection
    $('.recipe-ingredient-check').on('change', function () {
        updateRecipeSelection();
    });

    function updateRecipeSelection() {
        let selectedCount = 0;
        let estimatedTotal = 0;

        $('.recipe-ingredient-check:checked').each(function () {
            selectedCount++;
            const price = parseFloat($(this).data('price')) || 0;
            estimatedTotal += price;
        });

        $('#selectedIngredientCount').text(selectedCount);
        $('#estimatedIngredientsTotal').text('₱' + estimatedTotal.toFixed(2));
        $('#btnBatchAddCart').prop('disabled', selectedCount === 0);
    }

    // 5. Dual View Toggle (Card View vs Table View in Buyer Orders)
    $('#btnCardView').on('click', function () {
        $(this).addClass('active btn-farm-primary').removeClass('btn-farm-outline');
        $('#btnTableView').removeClass('active btn-farm-primary').addClass('btn-farm-outline');
        $('#ordersCardContainer').removeClass('d-none');
        $('#ordersTableContainer').addClass('d-none');
    });

    $('#btnTableView').on('click', function () {
        $(this).addClass('active btn-farm-primary').removeClass('btn-farm-outline');
        $('#btnCardView').removeClass('active btn-farm-primary').addClass('btn-farm-outline');
        $('#ordersTableContainer').removeClass('d-none');
        $('#ordersCardContainer').addClass('d-none');
    });

    // 6. Farmer Product Edit Modal population
    $(document).on('click', '.btn-edit-product', function () {
        const id = $(this).data('id');
        const name = $(this).data('name');
        const categoryId = $(this).data('category-id');
        const price = $(this).data('price');
        const quantity = $(this).data('quantity');
        const kilograms = $(this).data('kilograms');
        const unit = $(this).data('unit');
        const description = $(this).data('description');

        const $modal = $('#editProductModal');
        $modal.find('form').attr('action', '/farmer/products/' + id);
        $modal.find('#edit_name').val(name);
        $modal.find('#edit_category_id').val(categoryId);
        $modal.find('#edit_price').val(price);
        $modal.find('#edit_quantity').val(quantity);
        $modal.find('#edit_kilograms').val(kilograms);
        $modal.find('#edit_unit').val(unit);
        $modal.find('#edit_description').val(description);
    });

    // 7. Toast helper function
    window.showToast = function (title, message) {
        const toastHtml = `
            <div class="toast align-items-center text-white bg-farm-primary border-0 show" role="alert" aria-live="assertive" aria-atomic="true" style="position: fixed; bottom: 20px; right: 20px; z-index: 1090;">
                <div class="d-flex">
                    <div class="toast-body">
                        <strong>${title}:</strong> ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        `;
        const $toast = $(toastHtml).appendTo('body');
        setTimeout(() => {
            $toast.fadeOut(400, function () { $(this).remove(); });
        }, 3000);
    };
});
