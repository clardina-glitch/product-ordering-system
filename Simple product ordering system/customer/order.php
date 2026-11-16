<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('customer');

$pdo  = db();
$user = current_user();

$errors            = [];
$customerName      = $user['name'];
$customerEmail     = '';
$customerPhone     = '';
$customerAddress   = '';
$paymentMethod     = 'Cash';
$selectedProductId = 0;
$quantity          = 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $customerName      = request_string('customer_name');
    $customerEmail     = request_string('customer_email');
    $customerPhone     = request_string('customer_phone');
    $customerAddress   = request_string('customer_address');
    $paymentMethod     = request_string('payment_method');
    $selectedProductId = parse_positive_int($_POST['product_id'] ?? null) ?? 0;
    $quantity          = parse_positive_int($_POST['quantity'] ?? null) ?? 0;
    $q                 = '';

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

    if ($selectedProductId === 0) {
        $errors['product_id'] = 'Please select a product.';
    }

    if ($quantity === 0) {
        $errors['quantity'] = 'Quantity must be a whole number greater than zero.';
    } elseif ($quantity > 9999) {
        $errors['quantity'] = 'Quantity is too large.';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            $statement = $pdo->prepare('SELECT * FROM products WHERE id = ? FOR UPDATE');
            $statement->execute([$selectedProductId]);
            $product = $statement->fetch();

            if (!$product) {
                $errors['product_id'] = 'The selected product no longer exists.';
            } elseif (!(int) $product['is_available']) {
                $errors['product_id'] = 'The selected product is not available right now.';
            } elseif ((int) $product['stock'] < $quantity) {
                $errors['quantity'] = 'Only ' . (int) $product['stock'] . ' unit(s) of ' . $product['name'] . ' are in stock.';
            }

            if ($errors) {
                $pdo->rollBack();
            } else {
                $unitPrice = (float) $product['unit_price'];
                $total     = round($unitPrice * $quantity, 2);

                $insert = $pdo->prepare(
                    "INSERT INTO orders
                        (user_id, customer_name, customer_email, customer_phone, customer_address,
                         product_id, product_name, quantity, unit_price, total_amount, payment_method, status)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')"
                );
                $insert->execute([
                    $user['id'],
                    $customerName,
                    $customerEmail,
                    $customerPhone,
                    $customerAddress,
                    $selectedProductId,
                    $product['name'],
                    $quantity,
                    $unitPrice,
                    $total,
                    $paymentMethod,
                ]);

                $orderId = (int) $pdo->lastInsertId();

                $update = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ?');
                $update->execute([$quantity, $selectedProductId]);

                $pdo->commit();

                set_flash(
                    'success',
                    'Order ' . order_code($orderId) . ' placed for ' . $customerName . ' — ' . format_money($total)
                    . ' via ' . $paymentMethod . '. You can track it under My Orders.'
                );
                redirect('my_orders.php');
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Order creation failed: ' . $e->getMessage());
            set_flash('error', 'Something went wrong while saving the order. Please try again.');
            redirect('order.php');
        }
    }
} else {
    $q = trim((string) ($_GET['q'] ?? ''));

    // Prefill the delivery details from this customer's most recent order.
    $lastStatement = $pdo->prepare(
        'SELECT customer_name, customer_email, customer_phone, customer_address
         FROM orders
         WHERE user_id = ?
         ORDER BY id DESC
         LIMIT 1'
    );
    $lastStatement->execute([$user['id']]);
    $lastOrder = $lastStatement->fetch();

    if ($lastOrder) {
        $customerName    = (string) $lastOrder['customer_name'];
        $customerEmail   = (string) $lastOrder['customer_email'];
        $customerPhone   = (string) $lastOrder['customer_phone'];
        $customerAddress = (string) $lastOrder['customer_address'];
    }
}

$q = $q ?? '';

$sql = 'SELECT id, name, category, description, unit_price, stock
        FROM products
        WHERE is_available = 1';
$params = [];

if ($q !== '') {
    $sql .= ' AND (name LIKE :q1 OR category LIKE :q2 OR description LIKE :q3)';
    $like = '%' . $q . '%';
    $params = [':q1' => $like, ':q2' => $like, ':q3' => $like];
}

$sql .= ' ORDER BY stock > 0 DESC, name ASC';

$statement = $pdo->prepare($sql);
$statement->execute($params);
$products = $statement->fetchAll();

$selectedProduct = null;
foreach ($products as $product) {
    if ((int) $product['id'] === $selectedProductId) {
        $selectedProduct = $product;
        break;
    }
}

if ($selectedProduct !== null) {
    if ($quantity < 1) {
        $quantity = 1;
    }
    if ($quantity > (int) $selectedProduct['stock']) {
        $quantity = max(1, (int) $selectedProduct['stock']);
    }
}

$summaryTotal = $selectedProduct
    ? round((float) $selectedProduct['unit_price'] * $quantity, 2)
    : 0.0;

$pageTitle    = 'Place an Order';
$pageSubtitle = 'Choose a product, set the quantity and confirm the order summary.';
$activePage   = 'order';

require __DIR__ . '/../includes/header.php';
?>

<?php if ($errors): ?>
    <div class="flash flash-error" role="alert">
        <span class="flash-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16.5v.5"/></svg>
        </span>
        <span>Please fix the highlighted fields below.</span>
    </div>
<?php endif; ?>

<div class="toolbar">
    <form class="search-form" method="get" action="order.php" role="search">
        <span class="search-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
        </span>
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search products by name, category or description…" aria-label="Search products">
        <button class="btn btn-ghost btn-sm" type="submit">Search</button>
        <?php if ($q !== ''): ?>
            <a class="text-link" href="order.php">Clear</a>
        <?php endif; ?>
    </form>
    <span class="result-count"><?= count($products) ?> <?= count($products) === 1 ? 'product' : 'products' ?></span>
</div>

<form method="post" action="order.php" class="order-layout" id="orderForm" data-currency="<?= e(CURRENCY) ?>" novalidate>
    <?= csrf_field() ?>

    <section class="panel product-panel">
        <div class="panel-head">
            <div>
                <h3>Available products</h3>
                <p>Pick one product to include in this order.</p>
            </div>
            <span class="step-chip">Step 1 of 2</span>
        </div>

        <?php if (!$products): ?>
            <div class="empty-state empty-state-lg">
                <span class="empty-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="m3 8 9 5 9-5M12 13v8"/></svg>
                </span>
                <h4>No products found</h4>
                <p><?= $q !== '' ? 'Try a different search term.' : 'No products are available right now. Please check back later.' ?></p>
                <a class="btn btn-primary btn-sm" href="<?= $q !== '' ? 'order.php' : 'my_orders.php' ?>">
                    <?= $q !== '' ? 'Clear search' : 'View my orders' ?>
                </a>
            </div>
        <?php else: ?>
            <div class="product-grid">
                <?php foreach ($products as $product): ?>
                    <?php
                    $inStock  = (int) $product['stock'] > 0;
                    $isPicked = (int) $product['id'] === $selectedProductId;
                    ?>
                    <label class="product-card <?= $isPicked ? 'is-selected' : '' ?> <?= $inStock ? '' : 'is-unavailable' ?>">
                        <input class="product-radio" type="radio" name="product_id" value="<?= (int) $product['id'] ?>"
                               data-name="<?= e($product['name']) ?>"
                               data-category="<?= e($product['category']) ?>"
                               data-price="<?= e(number_format((float) $product['unit_price'], 2, '.', '')) ?>"
                               data-stock="<?= (int) $product['stock'] ?>"
                               <?= $isPicked ? 'checked' : '' ?>
                               <?= $inStock ? '' : 'disabled' ?>>
                        <span class="product-check">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" aria-hidden="true"><path d="m5 13 4 4L19 7"/></svg>
                        </span>
                        <span class="product-top">
                            <span class="pill"><?= e($product['category']) ?></span>
                            <span class="stock-chip <?= $inStock ? '' : 'is-out' ?>">
                                <?= $inStock ? (int) $product['stock'] . ' in stock' : 'Out of stock' ?>
                            </span>
                        </span>
                        <span class="product-name"><?= e($product['name']) ?></span>
                        <span class="product-desc"><?= e($product['description'] ?: 'No description provided.') ?></span>
                        <span class="product-bottom">
                            <strong><?= format_money($product['unit_price']) ?></strong>
                            <span class="select-label"><?= $isPicked ? 'Selected' : ($inStock ? 'Select' : 'Unavailable') ?></span>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <aside class="panel summary-panel">
        <div class="panel-head">
            <div>
                <h3>Order summary</h3>
                <p>Review the details before submitting.</p>
            </div>
            <span class="step-chip">Step 2 of 2</span>
        </div>

        <div class="summary-group-title">Customer details</div>

        <div class="field">
            <label for="customer_name">Customer name <span class="req">*</span></label>
            <input type="text" id="customer_name" name="customer_name" value="<?= e($customerName) ?>"
                   placeholder="e.g. Maria Santos" maxlength="100" autocomplete="name"
                   class="<?= isset($errors['customer_name']) ? 'is-invalid' : '' ?>">
            <?php if (isset($errors['customer_name'])): ?>
                <span class="field-error"><?= e($errors['customer_name']) ?></span>
            <?php endif; ?>
        </div>

        <div class="field">
            <label for="customer_email">Email address <span class="req">*</span></label>
            <input type="email" id="customer_email" name="customer_email" value="<?= e($customerEmail) ?>"
                   placeholder="e.g. maria@example.com" maxlength="120" autocomplete="email"
                   class="<?= isset($errors['customer_email']) ? 'is-invalid' : '' ?>">
            <?php if (isset($errors['customer_email'])): ?>
                <span class="field-error"><?= e($errors['customer_email']) ?></span>
            <?php endif; ?>
        </div>

        <div class="field">
            <label for="customer_phone">Phone number <span class="req">*</span></label>
            <input type="tel" id="customer_phone" name="customer_phone" value="<?= e($customerPhone) ?>"
                   placeholder="e.g. +63 917 123 4567" maxlength="30" autocomplete="tel" inputmode="tel"
                   class="<?= isset($errors['customer_phone']) ? 'is-invalid' : '' ?>">
            <?php if (isset($errors['customer_phone'])): ?>
                <span class="field-error"><?= e($errors['customer_phone']) ?></span>
            <?php endif; ?>
        </div>

        <div class="field">
            <label for="customer_address">Delivery address <span class="req">*</span></label>
            <textarea id="customer_address" name="customer_address" rows="2" maxlength="255"
                      placeholder="House no., street, barangay, city"
                      class="<?= isset($errors['customer_address']) ? 'is-invalid' : '' ?>"><?= e($customerAddress) ?></textarea>
            <?php if (isset($errors['customer_address'])): ?>
                <span class="field-error"><?= e($errors['customer_address']) ?></span>
            <?php endif; ?>
        </div>

        <div class="summary-group">
            <div class="summary-group-title">Payment method</div>
            <div class="payment-options">
                <label class="payment-option" for="payment_cash">
                    <input type="radio" id="payment_cash" name="payment_method" value="Cash"
                           <?= $paymentMethod === 'Cash' ? 'checked' : '' ?>>
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
                <label class="payment-option" for="payment_gcash">
                    <input type="radio" id="payment_gcash" name="payment_method" value="GCash"
                           <?= $paymentMethod === 'GCash' ? 'checked' : '' ?>>
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

        <div class="summary-group">
            <div class="summary-group-title">Product &amp; total</div>

            <div class="summary-product <?= $selectedProduct ? '' : 'is-empty' ?>" id="summaryProduct">
                <?php if ($selectedProduct): ?>
                    <span class="summary-thumb" id="summaryThumb"><?= e(mb_substr($selectedProduct['name'], 0, 1)) ?></span>
                    <span class="summary-product-meta">
                        <strong id="summaryName"><?= e($selectedProduct['name']) ?></strong>
                        <small id="summaryCategory"><?= e($selectedProduct['category']) ?> · <?= format_money($selectedProduct['unit_price']) ?> each</small>
                    </span>
                <?php else: ?>
                    <span class="summary-thumb" id="summaryThumb">?</span>
                    <span class="summary-product-meta">
                        <strong id="summaryName">No product selected</strong>
                        <small id="summaryCategory">Select a product from the list.</small>
                    </span>
                <?php endif; ?>
            </div>
            <?php if (isset($errors['product_id'])): ?>
                <span class="field-error"><?= e($errors['product_id']) ?></span>
            <?php endif; ?>

            <div class="summary-rows">
                <div class="field">
                    <label for="quantity">Quantity <span class="req">*</span></label>
                    <div class="stepper">
                        <button type="button" class="stepper-btn" data-step="-1" aria-label="Decrease quantity">−</button>
                        <input type="number" id="quantity" name="quantity" value="<?= (int) $quantity ?>" min="1"
                               max="<?= $selectedProduct ? (int) $selectedProduct['stock'] : 9999 ?>" step="1"
                               class="<?= isset($errors['quantity']) ? 'is-invalid' : '' ?>">
                        <button type="button" class="stepper-btn" data-step="1" aria-label="Increase quantity">+</button>
                    </div>
                    <?php if (isset($errors['quantity'])): ?>
                        <span class="field-error"><?= e($errors['quantity']) ?></span>
                    <?php endif; ?>
                    <span class="field-hint" id="stockHint"><?= $selectedProduct ? 'Maximum ' . (int) $selectedProduct['stock'] . ' available' : 'Select a product to see availability' ?></span>
                </div>

                <div class="summary-line">
                    <span>Unit price</span>
                    <strong id="unitPrice"><?= $selectedProduct ? format_money($selectedProduct['unit_price']) : '—' ?></strong>
                </div>
                <div class="summary-line">
                    <span>Quantity</span>
                    <strong id="summaryQty"><?= $selectedProduct ? (int) $quantity : '—' ?></strong>
                </div>
                <div class="summary-line">
                    <span>Payment method</span>
                    <strong id="summaryPayment"><?= e($paymentMethod) ?></strong>
                </div>
                <div class="summary-total">
                    <span>Total amount</span>
                    <strong id="totalAmount"><?= format_money($summaryTotal) ?></strong>
                </div>
            </div>
        </div>

        <button class="btn btn-primary btn-block btn-lg" type="submit">Submit order</button>
        <p class="summary-note">Stock is checked again when the order is submitted.</p>
    </aside>
</form>

<?php require __DIR__ . '/../includes/footer.php'; ?>
