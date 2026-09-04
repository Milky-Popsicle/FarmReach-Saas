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

    // 7. SweetAlert2 Universal Confirmation & Notification Helpers
    window.swalSuccess = function (title, text) {
        if (typeof Swal !== 'undefined') {
            return Swal.fire({
                icon: 'success',
                title: title,
                text: text,
                confirmButtonColor: '#1B4332'
            });
        }
    };

    window.swalError = function (title, text) {
        if (typeof Swal !== 'undefined') {
            return Swal.fire({
                icon: 'error',
                title: title,
                text: text,
                confirmButtonColor: '#1B4332'
            });
        }
    };

    window.swalWarning = function (title, text) {
        if (typeof Swal !== 'undefined') {
            return Swal.fire({
                icon: 'warning',
                title: title,
                text: text,
                confirmButtonColor: '#DAA520'
            });
        }
    };

    // Universal confirmation dialog for SweetAlert2
    $(document).on('submit', 'form.swal-confirm, form[data-confirm]', function (e) {
        e.preventDefault();
        const $form = $(this);
        const title = $form.data('confirm-title') || 'Are you sure?';
        const text = $form.data('confirm') || $form.data('confirm-text') || 'Do you want to proceed with this action?';
        const icon = $form.data('confirm-icon') || 'warning';
        const confirmBtnText = $form.data('confirm-btn') || 'Yes, proceed';

        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: title,
                text: text,
                icon: icon,
                showCancelButton: true,
                confirmButtonColor: '#1B4332',
                cancelButtonColor: '#6c757d',
                confirmButtonText: confirmBtnText,
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    $form.off('submit').submit();
                }
            });
        } else {
            if (confirm(text)) {
                $form.off('submit').submit();
            }
        }
    });

    // 8. Toast helper using SweetAlert2
    window.showToast = function (title, message, icon = 'success') {
        if (typeof Swal !== 'undefined') {
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
                didOpen: (toast) => {
                    toast.addEventListener('mouseenter', Swal.stopTimer);
                    toast.addEventListener('mouseleave', Swal.resumeTimer);
                }
            });

            Toast.fire({
                icon: icon,
                title: title ? `${title}: ${message}` : message
            });
        } else {
            alert(`${title}: ${message}`);
        }
    };
});
