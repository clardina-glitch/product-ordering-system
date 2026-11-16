<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('owner');

$pdo = db();

$orderId = parse_positive_int($_POST['id'] ?? $_GET['id'] ?? null);

if ($orderId === null) {
    set_flash('error', 'Invalid order selected.');
    redirect('orders.php');
}

$statement = $pdo->prepare('SELECT * FROM orders WHERE id = ? LIMIT 1');
$statement->execute([$orderId]);
$order = $statement->fetch();

if (!$order) {
    set_flash('error', 'That order could not be found.');
    redirect('orders.php');
}

$allowedStatuses = ['Pending', 'Completed', 'Cancelled'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $customerName = request_string('customer_name');
    $customerEmail = request_string('customer_email');
    $customerPhone = request_string('customer_phone');
    $customerAddress = request_string('customer_address');
    $paymentMethod = request_string('payment_method');
    $productId    = parse_positive_int($_POST['product_id'] ?? null) ?? 0;
    $quantity     = parse_positive_int($_POST['quantity'] ?? null) ?? 0;
    $status       = request_string('status');

    $errors = [];

    if ($customerName === '') {
        $errors['customer_name'] = 'Customer name is required.';
    } elseif (mb_strlen($customerName) < 2 || mb_strlen($customerName) > 100) {
        $errors['customer_name'] = 'Customer name must be between 2 and 100 characters.';
    } elseif (!preg_match("/^[\p{L}][\p{L}\p{M}\s.'-]*$/u", $customerName)) {
        $errors['customer_name'] = 'Customer name may only contain letters, spaces, periods, apostrophes and hyphens.';
    }

    $fieldError = customer_email_error($customerEmail);
    if ($fieldError !== null) {
        $errors['customer_email'] = $fieldError;
    }

    $fieldError = customer_phone_error($customerPhone);
    if ($fieldError !== null) {
        $errors['customer_phone'] = $fieldError;
    }

    $fieldError = customer_address_error($customerAddress);
    if ($fieldError !== null) {
        $errors['customer_address'] = $fieldError;
    }

    $fieldError = payment_method_error($paymentMethod);
    if ($fieldError !== null) {
        $errors['payment_method'] = $fieldError;
    }

    if ($productId === 0) {
        $errors['product_id'] = 'Please select a product.';
    }

    if ($quantity === 0) {
        $errors['quantity'] = 'Quantity must be a whole number greater than zero.';
    } elseif ($quantity > 9999) {
        $errors['quantity'] = 'Quantity is too large.';
    }

    if (!in_array($status, $allowedStatuses, true)) {
        $errors['status'] = 'Please choose a valid order status.';
    }

    if ($errors) {
        set_form_state($errors, [
            'customer_name' => $customerName,
            'customer_email' => $customerEmail,
            'customer_phone' => $customerPhone,
            'customer_address' => $customerAddress,
            'payment_method' => $paymentMethod,
            'product_id'    => $productId,
            'quantity'      => $quantity,
            'status'        => $status,
        ]);
        redirect('edit_order.php?id=' . $orderId);
    }

    try {
        $pdo->beginTransaction();

        $lockOrder = $pdo->prepare('SELECT * FROM orders WHERE id = ? FOR UPDATE');
        $lockOrder->execute([$orderId]);
        $lockedOrder = $lockOrder->fetch();

        if (!$lockedOrder) {
            $pdo->rollBack();
            set_flash('error', 'That order no longer exists.');
            redirect('orders.php');
        }

        $lockProduct = $pdo->prepare('SELECT * FROM products WHERE id = ? FOR UPDATE');
        $lockProduct->execute([$productId]);
        $product = $lockProduct->fetch();

        if (!$product) {
            $errors['product_id'] = 'The selected product no longer exists.';
        } else {
            $sameProduct      = (int) $lockedOrder['product_id'] === $productId;
            $oldReservesStock = $lockedOrder['status'] !== 'Cancelled';
            $newReservesStock = $status !== 'Cancelled';

            
            
            $reservedQty  = $sameProduct && $oldReservesStock ? (int) $lockedOrder['quantity'] : 0;
            $availableQty = (int) $product['stock'] + $reservedQty;

            if (!$sameProduct && !(int) $product['is_available']) {
                $errors['product_id'] = 'That product is not available for new orders.';
            } elseif ($newReservesStock && $quantity > $availableQty) {
                $errors['quantity'] = 'Only ' . $availableQty . ' unit(s) of ' . $product['name'] . ' are available for this order.';
            }
        }

        if ($errors) {
            $pdo->rollBack();
            set_form_state($errors, [
                'customer_name' => $customerName,
                'customer_email' => $customerEmail,
                'customer_phone' => $customerPhone,
                'customer_address' => $customerAddress,
                'payment_method' => $paymentMethod,
                'product_id'    => $productId,
                'quantity'      => $quantity,
                'status'        => $status,
            ]);
            redirect('edit_order.php?id=' . $orderId);
        }

        $unitPrice    = (float) $product['unit_price'];
        $total        = round($unitPrice * $quantity, 2);
        $oldQty       = (int) $lockedOrder['quantity'];
        $oldProductId = $lockedOrder['product_id'] !== null ? (int) $lockedOrder['product_id'] : null;
        $oldReserved  = $lockedOrder['status'] !== 'Cancelled';
        $newReserved  = $status !== 'Cancelled';

        
        
        if ($oldReserved && $oldProductId !== null) {
            $restore = $pdo->prepare('UPDATE products SET stock = stock + ? WHERE id = ?');
            $restore->execute([$oldQty, $oldProductId]);
        }

        if ($newReserved) {
            $deduct = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ?');
            $deduct->execute([$quantity, $productId]);
        }

        $update = $pdo->prepare(
            'UPDATE orders
             SET customer_name = ?, customer_email = ?, customer_phone = ?, customer_address = ?,
                 product_id = ?, product_name = ?, quantity = ?, unit_price = ?, total_amount = ?,
                 payment_method = ?, status = ?
             WHERE id = ?'
        );
        $update->execute([
            $customerName,
            $customerEmail,
            $customerPhone,
            $customerAddress,
            $productId,
            $product['name'],
            $quantity,
            $unitPrice,
            $total,
            $paymentMethod,
            $status,
            $orderId,
        ]);

        $pdo->commit();

        set_flash('success', 'Order ' . order_code($orderId) . ' was updated successfully.');
        redirect('orders.php');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Order update failed: ' . $e->getMessage());
        set_flash('error', 'Something went wrong while updating the order. Please try again.');
        redirect('edit_order.php?id=' . $orderId);
    }
}

$formState = take_form_state();
$errors    = $formState['errors'];
$form      = array_merge([
    'customer_name' => $order['customer_name'],
    'customer_email' => $order['customer_email'] ?? '',
    'customer_phone' => $order['customer_phone'] ?? '',
    'customer_address' => $order['customer_address'] ?? '',
    'payment_method' => $order['payment_method'] ?? 'Cash',
    'product_id'    => (int) ($order['product_id'] ?? 0),
    'quantity'      => (int) $order['quantity'],
    'status'        => $order['status'],
], $formState['old']);

$productsStatement = $pdo->prepare(
    'SELECT id, name, category, unit_price, stock, is_available
     FROM products
     WHERE is_available = 1 OR id = ?
     ORDER BY name ASC'
);
$productsStatement->execute([$form['product_id']]);
$products = $productsStatement->fetchAll();

$selectedProduct = null;
foreach ($products as $product) {
    if ((int) $product['id'] === (int) $form['product_id']) {
        $selectedProduct = $product;
        break;
    }
}

$reservedQty  = $selectedProduct
    && (int) $selectedProduct['id'] === (int) ($order['product_id'] ?? 0)
    && $order['status'] !== 'Cancelled'
    ? (int) $order['quantity']
    : 0;
$availableQty = $selectedProduct ? (int) $selectedProduct['stock'] + $reservedQty : 0;
$unitPrice    = $selectedProduct ? (float) $selectedProduct['unit_price'] : (float) $order['unit_price'];
$previewTotal = round($unitPrice * (int) $form['quantity'], 2);

$pageTitle    = 'Edit ' . order_code($orderId);
$pageSubtitle = 'Update the customer, product, quantity or status of this order.';
$activePage   = 'orders';

require __DIR__ . '/../includes/header.php';
?>

<div class="page-back">
    <a class="back-link" href="orders.php">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
        Back to orders
    </a>
</div>

<?php if ($errors): ?>
    <div class="flash flash-error" role="alert">
        <span class="flash-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16.5v.5"/></svg>
        </span>
        <span>Please fix the highlighted fields below.</span>
    </div>
<?php endif; ?>

<form method="post" action="edit_order.php" class="edit-layout" id="editOrderForm" data-currency="<?= e(CURRENCY) ?>" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $orderId ?>">

    <section class="panel">
        <div class="panel-head">
            <div>
                <h3>Order details</h3>
                <p>Order <?= e(order_code($orderId)) ?> · placed <?= e(format_date($order['created_at'])) ?>.</p>
            </div>
            <span class="badge <?= status_class($order['status']) ?>">Current: <?= e($order['status']) ?></span>
        </div>

        <div class="form-grid">
            <div class="field field-full">
                <label for="customer_name">Customer name <span class="req">*</span></label>
                <input type="text" id="customer_name" name="customer_name" value="<?= e($form['customer_name']) ?>"
                       maxlength="100" autocomplete="name"
                       class="<?= isset($errors['customer_name']) ? 'is-invalid' : '' ?>">
                <?php if (isset($errors['customer_name'])): ?>
                    <span class="field-error"><?= e($errors['customer_name']) ?></span>
                <?php endif; ?>
            </div>

            <div class="field">
                <label for="customer_email">Email address <span class="req">*</span></label>
                <input type="email" id="customer_email" name="customer_email" value="<?= e($form['customer_email']) ?>"
                       placeholder="e.g. maria@example.com" maxlength="120" autocomplete="email"
                       class="<?= isset($errors['customer_email']) ? 'is-invalid' : '' ?>">
                <?php if (isset($errors['customer_email'])): ?>
                    <span class="field-error"><?= e($errors['customer_email']) ?></span>
                <?php endif; ?>
            </div>

            <div class="field">
                <label for="customer_phone">Phone number <span class="req">*</span></label>
                <input type="tel" id="customer_phone" name="customer_phone" value="<?= e($form['customer_phone']) ?>"
                       placeholder="e.g. +63 917 123 4567" maxlength="30" autocomplete="tel" inputmode="tel"
                       class="<?= isset($errors['customer_phone']) ? 'is-invalid' : '' ?>">
                <?php if (isset($errors['customer_phone'])): ?>
                    <span class="field-error"><?= e($errors['customer_phone']) ?></span>
                <?php endif; ?>
            </div>

            <div class="field field-full">
                <label for="customer_address">Delivery address <span class="req">*</span></label>
                <textarea id="customer_address" name="customer_address" rows="2" maxlength="255"
                          placeholder="House no., street, barangay, city"
                          class="<?= isset($errors['customer_address']) ? 'is-invalid' : '' ?>"><?= e($form['customer_address']) ?></textarea>
                <?php if (isset($errors['customer_address'])): ?>
                    <span class="field-error"><?= e($errors['customer_address']) ?></span>
                <?php endif; ?>
            </div>

            <div class="field field-full">
                <label>Payment method <span class="req">*</span></label>
                <div class="payment-options">
                    <label class="payment-option" for="edit_payment_cash">
                        <input type="radio" id="edit_payment_cash" name="payment_method" value="Cash"
                               <?= $form['payment_method'] === 'Cash' ? 'checked' : '' ?>>
                        <span class="payment-card">
                            <span class="payment-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 12h.01M18 12h.01"/></svg>
                            </span>
                            <span class="payment-text">
                                <strong>Cash</strong>
                                <small>On delivery</small>
                            </span>
                        </span>
                    </label>
                    <label class="payment-option" for="edit_payment_gcash">
                        <input type="radio" id="edit_payment_gcash" name="payment_method" value="GCash"
                               <?= $form['payment_method'] === 'GCash' ? 'checked' : '' ?>>
                        <span class="payment-card">
                            <span class="payment-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="5" y="2" width="14" height="20" rx="3"/><path d="M10 18h4"/></svg>
                            </span>
                            <span class="payment-text">
                                <strong>GCash</strong>
                                <small>Mobile wallet</small>
                            </span>
                        </span>
                    </label>
                </div>
                <?php if (isset($errors['payment_method'])): ?>
                    <span class="field-error"><?= e($errors['payment_method']) ?></span>
                <?php endif; ?>
            </div>

            <div class="field field-full">
                <label for="product_id">Product <span class="req">*</span></label>
                <select id="product_id" name="product_id" class="select select-full <?= isset($errors['product_id']) ? 'is-invalid' : '' ?>">
                    <option value="">Select a product…</option>
                    <?php foreach ($products as $product): ?>
                        <option value="<?= (int) $product['id'] ?>"
                                data-price="<?= e(number_format((float) $product['unit_price'], 2, '.', '')) ?>"
                                data-stock="<?= (int) $product['stock'] ?>"
                                data-reserved="<?= (int) $product['id'] === (int) ($order['product_id'] ?? 0) && $order['status'] !== 'Cancelled' ? (int) $order['quantity'] : 0 ?>"
                                <?= (int) $product['id'] === (int) $form['product_id'] ? 'selected' : '' ?>>
                            <?= e($product['name']) ?> — <?= format_money($product['unit_price']) ?><?= !(int) $product['is_available'] ? ' (unavailable)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['product_id'])): ?>
                    <span class="field-error"><?= e($errors['product_id']) ?></span>
                <?php endif; ?>
            </div>

            <div class="field">
                <label for="quantity">Quantity <span class="req">*</span></label>
                <input type="number" id="quantity" name="quantity" value="<?= (int) $form['quantity'] ?>"
                       min="1" max="<?= $availableQty > 0 ? $availableQty : 9999 ?>" step="1"
                       class="<?= isset($errors['quantity']) ? 'is-invalid' : '' ?>">
                <?php if (isset($errors['quantity'])): ?>
                    <span class="field-error"><?= e($errors['quantity']) ?></span>
                <?php endif; ?>
                <span class="field-hint" id="editStockHint"><?= $selectedProduct ? $availableQty . ' unit(s) available for this order' : 'Select a product to see availability' ?></span>
            </div>

            <div class="field">
                <label for="status">Order status <span class="req">*</span></label>
                <select id="status" name="status" class="select select-full <?= isset($errors['status']) ? 'is-invalid' : '' ?>">
                    <?php foreach ($allowedStatuses as $option): ?>
                        <option value="<?= e($option) ?>" <?= $form['status'] === $option ? 'selected' : '' ?>><?= e($option) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['status'])): ?>
                    <span class="field-error"><?= e($errors['status']) ?></span>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-actions">
            <button class="btn btn-primary" type="submit">Save changes</button>
            <a class="btn btn-ghost" href="orders.php">Cancel</a>
        </div>
    </section>

    <aside class="panel summary-panel">
        <div class="panel-head">
            <div>
                <h3>Updated summary</h3>
                <p>Recalculated from the current product price.</p>
            </div>
        </div>

        <div class="summary-product <?= $selectedProduct ? '' : 'is-empty' ?>" id="editSummaryProduct">
            <span class="summary-thumb"><?= $selectedProduct ? e(mb_substr($selectedProduct['name'], 0, 1)) : '?' ?></span>
            <span class="summary-product-meta">
                <strong id="editSummaryName"><?= $selectedProduct ? e($selectedProduct['name']) : 'No product selected' ?></strong>
                <small id="editSummaryCategory"><?= $selectedProduct ? e($selectedProduct['category']) : 'Choose a product to continue' ?></small>
            </span>
        </div>

        <div class="summary-rows">
            <div class="summary-line">
                <span>Unit price</span>
                <strong id="editUnitPrice"><?= $selectedProduct ? format_money($unitPrice) : '—' ?></strong>
            </div>
            <div class="summary-line">
                <span>Quantity</span>
                <strong id="editSummaryQty"><?= (int) $form['quantity'] ?></strong>
            </div>
            <div class="summary-line">
                <span>Payment method</span>
                <strong id="editSummaryPayment"><?= e($form['payment_method']) ?></strong>
            </div>
            <div class="summary-total">
                <span>Total amount</span>
                <strong id="editTotal"><?= format_money($previewTotal) ?></strong>
            </div>
        </div>

        <div class="order-meta">
            <div><span>Order ID</span><strong><?= e(order_code($orderId)) ?></strong></div>
            <div><span>Created</span><strong><?= e(format_date($order['created_at'], 'M j, Y g:i A')) ?></strong></div>
        </div>
    </aside>
</form>

<div class="danger-zone">
    <div>
        <strong>Delete this order</strong>
        <p>The order will be removed permanently and its stock returned to inventory.</p>
    </div>
    <form method="post" action="delete_order.php" data-confirm="Delete <?= e(order_code($orderId)) ?>? Stock will be returned to inventory.">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $orderId ?>">
        <button class="btn btn-danger" type="submit">Delete order</button>
    </form>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
