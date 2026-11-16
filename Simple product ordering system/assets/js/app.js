
(function () {
    'use strict';

    
    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('sidebarOverlay');
    var menuBtn = document.getElementById('menuBtn');

    function openSidebar() {
        if (!sidebar || !overlay) return;
        sidebar.classList.add('is-open');
        overlay.hidden = false;
        document.body.classList.add('nav-open');
        if (menuBtn) menuBtn.setAttribute('aria-expanded', 'true');
    }

    function closeSidebar() {
        if (!sidebar || !overlay) return;
        sidebar.classList.remove('is-open');
        overlay.hidden = true;
        document.body.classList.remove('nav-open');
        if (menuBtn) menuBtn.setAttribute('aria-expanded', 'false');
    }

    if (menuBtn) {
        menuBtn.addEventListener('click', function () {
            if (sidebar && sidebar.classList.contains('is-open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });
    }

    if (overlay) overlay.addEventListener('click', closeSidebar);

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') closeSidebar();
    });

    if (sidebar) {
        sidebar.querySelectorAll('.nav-link').forEach(function (link) {
            link.addEventListener('click', closeSidebar);
        });
    }

    window.addEventListener('resize', function () {
        if (window.innerWidth > 1024) closeSidebar();
    });

    
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            var message = form.getAttribute('data-confirm') || 'Are you sure?';
            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });

    
    document.querySelectorAll('.flash-stack .flash').forEach(function (flash, index) {
        if (flash.classList.contains('flash-error')) return;
        window.setTimeout(function () {
            flash.classList.add('is-hiding');
            window.setTimeout(function () {
                flash.remove();
            }, 400);
        }, 6000 + index * 400);
    });

    
    document.querySelectorAll('.password-toggle').forEach(function (toggle) {
        var input = document.getElementById(toggle.getAttribute('data-target'));

        if (!input) return;

        toggle.addEventListener('click', function () {
            var showing = input.type === 'text';
            input.type = showing ? 'password' : 'text';
            toggle.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
            input.focus();
        });
    });

    
    function money(value, currency) {
        var amount = Number(value);
        if (!isFinite(amount)) amount = 0;
        return currency + amount.toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function intValue(value, fallback) {
        var parsed = parseInt(value, 10);
        return isFinite(parsed) ? parsed : fallback;
    }

    function bindPaymentSummary(form, output) {
        if (!form || !output) return;

        form.querySelectorAll('input[name="payment_method"]').forEach(function (radio) {
            radio.addEventListener('change', function () {
                output.textContent = radio.value;
            });
        });

        var checked = form.querySelector('input[name="payment_method"]:checked');
        if (checked) output.textContent = checked.value;
    }

    
    var orderForm = document.getElementById('orderForm');

    if (orderForm) {
        var currency = orderForm.getAttribute('data-currency') || '';
        var radios = Array.prototype.slice.call(orderForm.querySelectorAll('.product-radio'));
        var cards = radios.map(function (radio) { return radio.closest('.product-card'); });

        var qtyInput    = document.getElementById('quantity');
        var nameOutput  = document.getElementById('summaryName');
        var catOutput   = document.getElementById('summaryCategory');
        var thumbOutput = document.getElementById('summaryThumb');
        var priceOutput = document.getElementById('unitPrice');
        var qtyOutput   = document.getElementById('summaryQty');
        var totalOutput = document.getElementById('totalAmount');
        var stockHint   = document.getElementById('stockHint');
        var productBox  = document.getElementById('summaryProduct');

        var currentPrice = 0;
        var currentStock = 0;

        function checkedRadio() {
            return radios.filter(function (radio) { return radio.checked; })[0] || null;
        }

        function refreshTotal() {
            var qty = intValue(qtyInput.value, 0);
            qtyOutput.textContent = qty > 0 ? String(qty) : '—';
            totalOutput.textContent = money(qty > 0 ? currentPrice * qty : 0, currency);
        }

        function applySelection(radio) {
            cards.forEach(function (card) {
                if (!card) return;
                card.classList.toggle('is-selected', !!radio && card.contains(radio));

                var label = card.querySelector('.select-label');
                var cardRadio = card.querySelector('.product-radio');
                if (label && cardRadio) {
                    if (cardRadio.disabled) {
                        label.textContent = 'Unavailable';
                    } else if (radio && cardRadio === radio) {
                        label.textContent = 'Selected';
                    } else {
                        label.textContent = 'Select';
                    }
                }
            });

            if (!radio) {
                currentPrice = 0;
                currentStock = 0;
                productBox.classList.add('is-empty');
                nameOutput.textContent = 'No product selected';
                catOutput.textContent = 'Select a product from the list.';
                thumbOutput.textContent = '?';
                priceOutput.textContent = '—';
                stockHint.textContent = 'Select a product to see availability';
                qtyInput.max = '9999';
                refreshTotal();
                return;
            }

            currentPrice = parseFloat(radio.getAttribute('data-price')) || 0;
            currentStock = intValue(radio.getAttribute('data-stock'), 0);

            productBox.classList.remove('is-empty');
            nameOutput.textContent = radio.getAttribute('data-name') || '';
            catOutput.textContent = (radio.getAttribute('data-category') || '') + ' · ' + money(currentPrice, currency) + ' each';
            thumbOutput.textContent = (radio.getAttribute('data-name') || '?').charAt(0).toUpperCase();
            priceOutput.textContent = money(currentPrice, currency);
            qtyInput.max = String(currentStock);
            stockHint.textContent = currentStock > 0
                ? 'Maximum ' + currentStock + ' available'
                : 'This product is out of stock';

            if (intValue(qtyInput.value, 1) > currentStock) {
                qtyInput.value = String(Math.max(currentStock, 1));
            }

            refreshTotal();
        }

        radios.forEach(function (radio) {
            radio.addEventListener('change', function () {
                applySelection(radio);
            });
        });

        if (qtyInput) {
            qtyInput.addEventListener('input', refreshTotal);
            qtyInput.addEventListener('change', function () {
                var qty = intValue(qtyInput.value, 1);
                if (qty < 1) qty = 1;
                if (currentStock > 0 && qty > currentStock) qty = currentStock;
                qtyInput.value = String(qty);
                refreshTotal();
            });
        }

        orderForm.querySelectorAll('.stepper-btn').forEach(function (button) {
            button.addEventListener('click', function () {
                var step = intValue(button.getAttribute('data-step'), 0);
                var qty = intValue(qtyInput.value, 1) + step;
                if (qty < 1) qty = 1;
                if (currentStock > 0 && qty > currentStock) qty = currentStock;
                qtyInput.value = String(qty);
                refreshTotal();
            });
        });

        var selected = checkedRadio();
        if (selected) {
            applySelection(selected);
        } else {
            refreshTotal();
        }

        bindPaymentSummary(orderForm, document.getElementById('summaryPayment'));

        orderForm.addEventListener('submit', function (event) {
            if (!checkedRadio()) {
                event.preventDefault();
                window.alert('Please select a product before submitting the order.');
                return;
            }

            var qty = intValue(qtyInput.value, 0);
            if (qty < 1) {
                event.preventDefault();
                qtyInput.focus();
                return;
            }

            if (currentStock > 0 && qty > currentStock) {
                event.preventDefault();
                window.alert('Only ' + currentStock + ' unit(s) are available.');
                qtyInput.focus();
            }
        });
    }

    
    var editForm = document.getElementById('editOrderForm');

    if (editForm) {
        var editCurrency = editForm.getAttribute('data-currency') || '';
        var productSelect = document.getElementById('product_id');
        var editQty       = document.getElementById('quantity');
        var editName      = document.getElementById('editSummaryName');
        var editCategory  = document.getElementById('editSummaryCategory');
        var editThumb     = editForm.querySelector('#editSummaryProduct .summary-thumb');
        var editPrice     = document.getElementById('editUnitPrice');
        var editQtyOutput = document.getElementById('editSummaryQty');
        var editTotal     = document.getElementById('editTotal');
        var editHint      = document.getElementById('editStockHint');
        var editBox       = document.getElementById('editSummaryProduct');

        var editAvailable = 0;
        var editUnit      = 0;

        function updateEditSummary() {
            var option = productSelect.options[productSelect.selectedIndex];

            if (!option || !option.value) {
                editAvailable = 0;
                editUnit = 0;
                editBox.classList.add('is-empty');
                editName.textContent = 'No product selected';
                editCategory.textContent = 'Choose a product to continue';
                editThumb.textContent = '?';
                editPrice.textContent = '—';
                editHint.textContent = 'Select a product to see availability';
                editQtyOutput.textContent = String(intValue(editQty.value, 0) || 0);
                editTotal.textContent = money(0, editCurrency);
                return;
            }

            editUnit = parseFloat(option.getAttribute('data-price')) || 0;
            var stock = intValue(option.getAttribute('data-stock'), 0);
            var reserved = intValue(option.getAttribute('data-reserved'), 0);
            editAvailable = stock + reserved;

            editBox.classList.remove('is-empty');
            editName.textContent = option.textContent.split(' — ')[0];
            editCategory.textContent = option.textContent.indexOf('(unavailable)') !== -1
                ? 'Currently hidden from ordering'
                : 'Available for this order';
            editThumb.textContent = editName.textContent.charAt(0).toUpperCase();
            editPrice.textContent = money(editUnit, editCurrency);
            editQty.max = String(Math.max(editAvailable, 1));
            editHint.textContent = editAvailable > 0
                ? editAvailable + ' unit(s) available for this order'
                : 'This product is out of stock';

            if (intValue(editQty.value, 1) > editAvailable) {
                editQty.value = String(Math.max(editAvailable, 1));
            }

            var qty = intValue(editQty.value, 0);
            editQtyOutput.textContent = qty > 0 ? String(qty) : '—';
            editTotal.textContent = money(qty > 0 ? editUnit * qty : 0, editCurrency);
        }

        if (productSelect && editQty) {
            productSelect.addEventListener('change', updateEditSummary);

            editQty.addEventListener('input', function () {
                var qty = intValue(editQty.value, 0);
                editQtyOutput.textContent = qty > 0 ? String(qty) : '—';
                editTotal.textContent = money(qty > 0 ? editUnit * qty : 0, editCurrency);
            });

            editQty.addEventListener('change', function () {
                var qty = intValue(editQty.value, 1);
                if (qty < 1) qty = 1;
                if (editAvailable > 0 && qty > editAvailable) qty = editAvailable;
                editQty.value = String(qty);
                updateEditSummary();
            });

            editForm.addEventListener('submit', function (event) {
                var qty = intValue(editQty.value, 0);
                if (qty < 1) {
                    event.preventDefault();
                    editQty.focus();
                    return;
                }
                if (editAvailable > 0 && qty > editAvailable) {
                    event.preventDefault();
                    window.alert('Only ' + editAvailable + ' unit(s) are available for this order.');
                    editQty.focus();
                }
            });

            updateEditSummary();
        }

        bindPaymentSummary(editForm, document.getElementById('editSummaryPayment'));
    }
})();
