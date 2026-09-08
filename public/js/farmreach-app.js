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
                    const $badge = $('.cart-badge-count');
                    $badge.text(res.cart_count).show();
                    // Trigger CSS bounce micro-animation
                    $badge.removeClass('badge-bounce');
                    void $badge[0].offsetWidth; // force reflow
                    $badge.addClass('badge-bounce');

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
        const $form = $(this);
        if ($form.data('confirmed')) {
            return true;
        }

        e.preventDefault();
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
                    $form.data('confirmed', true);
                    $form[0].submit();
                }
            });
        } else {
            if (confirm(text)) {
                $form.data('confirmed', true);
                $form[0].submit();
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

    // 9. Password Show / Hide Eye Toggle with active state indicator & mobile touch support
    window.togglePasswordVisibility = function (trigger, event) {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }

        const btn = (trigger && trigger.closest) ? trigger.closest('.btn-toggle-password') : trigger;
        if (!btn) return;

        const targetSelector = btn.getAttribute('data-target') || btn.getAttribute('data-bs-target');
        let input = null;
        if (targetSelector) {
            try {
                input = document.querySelector(targetSelector);
            } catch (err) {
                // Ignore querySelector syntax error if any
            }
        }
        if (!input) {
            const group = btn.closest('.input-group') || btn.parentElement;
            if (group) {
                input = group.querySelector('input[type="password"], input[type="text"]');
            }
        }
        if (!input) return;

        const isPassword = input.getAttribute('type') === 'password';
        input.setAttribute('type', isPassword ? 'text' : 'password');

        const icon = btn.querySelector('i') || btn.querySelector('.bi');
        if (icon) {
            if (isPassword) {
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash-fill');
            } else {
                icon.classList.remove('bi-eye-slash-fill', 'bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        }

        if (isPassword) {
            btn.classList.add('is-active');
            btn.setAttribute('title', 'Hide Password');
            btn.setAttribute('aria-label', 'Hide Password');
            btn.setAttribute('aria-pressed', 'true');
        } else {
            btn.classList.remove('is-active');
            btn.setAttribute('title', 'Show Password');
            btn.setAttribute('aria-label', 'Show Password');
            btn.setAttribute('aria-pressed', 'false');
        }
    };

    $(document).on('click', '.btn-toggle-password', function (e) {
        window.togglePasswordVisibility(this, e);
    });

    // 10. Admin Announcement Edit Modal population
    $(document).on('click', '.btn-edit-announcement', function () {
        const id = $(this).data('id');
        const title = $(this).data('title');
        const location = $(this).data('location');
        const eventDate = $(this).data('event-date');
        const description = $(this).data('description');

        const $modal = $('#editAnnouncementModal');
        $modal.find('form').attr('action', '/admin/announcements/' + id);
        $modal.find('#edit_announcement_title').val(title);
        $modal.find('#edit_announcement_location').val(location);
        $modal.find('#edit_announcement_event_date').val(eventDate);
        $modal.find('#edit_announcement_description').val(description);
    });

    // 11. Clickable Card Navigation (Make whole card clickable without interfering with buttons/inputs)
    $(document).on('click', '.farm-card-interactive[data-url]', function (e) {
        if ($(e.target).closest('button, a, form, input, select, label, .dropdown, .btn').length) {
            return;
        }
        const url = $(this).data('url');
        if (url) {
            window.location.href = url;
        }
    });

    // 12. Real-time Product Subtotal & Weight Calculator (Product Detail Page)
    function updateProductSubtotal() {
        const $calcContainer = $('.product-price-calculator');
        if ($calcContainer.length === 0) return;

        const unitPrice = parseFloat($calcContainer.data('unit-price')) || 0;
        const unitKg = parseFloat($calcContainer.data('unit-kg')) || 1;
        const $qtyInput = $calcContainer.find('.qty-input');
        const qty = parseInt($qtyInput.val()) || 1;

        const totalKg = (unitKg * qty).toFixed(1);
        const subtotal = (unitPrice * qty).toFixed(2);

        $calcContainer.find('#dynamicWeight').text(totalKg);
        $calcContainer.find('#dynamicSubtotal').text('₱' + Number(subtotal).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
    }

    $(document).on('change input', '.product-price-calculator .qty-input', function () {
        updateProductSubtotal();
    });

    $(document).on('click', '.product-price-calculator .btn-qty-plus, .product-price-calculator .btn-qty-minus', function () {
        setTimeout(updateProductSubtotal, 50);
    });

    // Initial calculation check on page load
    updateProductSubtotal();

    // 13. Image Lightbox Zoom Preview Modal
    $(document).on('click', '.img-zoomable', function (e) {
        e.preventDefault();
        const imgSrc = $(this).attr('src') || $(this).data('src');
        const imgTitle = $(this).attr('alt') || $(this).data('title') || 'Product Image Preview';

        if (!imgSrc) return;

        $('#imagePreviewModalSrc').attr('src', imgSrc);
        $('#imagePreviewModalTitle').text(imgTitle);

        const modalEl = document.getElementById('imagePreviewModal');
        if (modalEl) {
            const previewModal = new bootstrap.Modal(modalEl);
            previewModal.show();
        }
    });

    // 14. Smooth Back-to-Top Floating Button
    const $backToTopBtn = $('#btnBackToTop');
    $(window).on('scroll', function () {
        if ($(this).scrollTop() > 300) {
            $backToTopBtn.addClass('show');
        } else {
            $backToTopBtn.removeClass('show');
        }
    });

    $backToTopBtn.on('click', function () {
        $('html, body').animate({ scrollTop: 0 }, 400);
    });

    // 15. Instant Live File Upload Image Preview
    $(document).on('change', '.input-img-preview', function () {
        const input = this;
        const targetId = $(this).data('preview-target');
        const $previewImg = $(targetId);

        if (input.files && input.files[0] && $previewImg.length) {
            const reader = new FileReader();
            reader.onload = function (e) {
                $previewImg.attr('src', e.target.result).closest('.image-preview-container').removeClass('d-none');
            };
            reader.readAsDataURL(input.files[0]);
        }
    });

    // 16. Table Batch Checkbox Row Select & Highlight
    $(document).on('change', '.check-select-all', function () {
        const isChecked = $(this).is(':checked');
        const targetTable = $(this).data('target-table') || 'table';
        $(targetTable).find('.check-select-item').prop('checked', isChecked).closest('tr').toggleClass('table-active', isChecked);
    });

    $(document).on('change', '.check-select-item', function () {
        const isChecked = $(this).is(':checked');
        $(this).closest('tr').toggleClass('table-active', isChecked);

        const targetTable = $(this).closest('table');
        const totalItems = targetTable.find('.check-select-item').length;
        const checkedItems = targetTable.find('.check-select-item:checked').length;
        targetTable.find('.check-select-all').prop('checked', totalItems > 0 && totalItems === checkedItems);
    });
});


