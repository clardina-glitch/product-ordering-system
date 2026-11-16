<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('owner');

$pdo = db();


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = request_string('action');

    if ($action === 'delete') {
        $deleteId = parse_positive_int($_POST['id'] ?? null);

        if ($deleteId === null) {
            set_flash('error', 'Invalid product selected.');
            redirect('products.php');
        }

        try {
            $statement = $pdo->prepare('DELETE FROM products WHERE id = ?');
            $statement->execute([$deleteId]);

            if ($statement->rowCount() === 0) {
                set_flash('error', 'That product no longer exists.');
            } else {
                set_flash('success', 'Product deleted. Existing orders keep their saved product details.');
            }
        } catch (Throwable $e) {
            error_log('Product deletion failed: ' . $e->getMessage());
            set_flash('error', 'Unable to delete that product. Please try again.');
        }

        redirect('products.php');
    }

    if ($action === 'save') {
        $productId   = parse_positive_int($_POST['id'] ?? null) ?? 0;
        $name        = request_string('name');
        $category    = request_string('category');
        $description = request_string('description');
        $priceRaw    = request_string('unit_price');
        $stockRaw    = request_string('stock');
        $isAvailable = isset($_POST['is_available']) ? 1 : 0;

        $errors = [];
        $old    = [
            'name'         => $name,
            'category'     => $category,
            'description'  => $description,
            'unit_price'   => $priceRaw,
            'stock'        => $stockRaw,
            'is_available' => $isAvailable,
        ];

        if ($name === '') {
            $errors['name'] = 'Product name is required.';
        } elseif (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
            $errors['name'] = 'Product name must be between 2 and 120 characters.';
        }

        if ($category === '') {
            $errors['category'] = 'Category is required.';
        } elseif (mb_strlen($category) > 60) {
            $errors['category'] = 'Category must be 60 characters or fewer.';
        }

        if (mb_strlen($description) > 255) {
            $errors['description'] = 'Description must be 255 characters or fewer.';
        }

        $unitPrice = parse_money($priceRaw);
        if ($unitPrice === null) {
            $errors['unit_price'] = 'Enter a valid price with up to 2 decimal places.';
        } elseif ($unitPrice <= 0) {
            $errors['unit_price'] = 'Unit price must be greater than zero.';
        }

        if (!preg_match('/^\d{1,7}$/', $stockRaw)) {
            $errors['stock'] = 'Stock must be a whole number between 0 and 9,999,999.';
        }

        if ($errors) {
            set_form_state($errors, $old);
            redirect('products.php' . ($productId > 0 ? '?edit=' . $productId : ''));
        }

        try {
            if ($productId > 0) {
                $statement = $pdo->prepare(
                    'UPDATE products
                     SET name = ?, category = ?, description = ?, unit_price = ?, stock = ?, is_available = ?
                     WHERE id = ?'
                );
                $statement->execute([$name, $category, $description, $unitPrice, (int) $stockRaw, $isAvailable, $productId]);

                if ($statement->rowCount() === 0) {
                    $check = $pdo->prepare('SELECT id FROM products WHERE id = ?');
                    $check->execute([$productId]);
                    if (!$check->fetch()) {
                        set_flash('error', 'That product no longer exists.');
                        redirect('products.php');
                    }
                }

                
                $sync = $pdo->prepare('UPDATE orders SET product_name = ? WHERE product_id = ?');
                $sync->execute([$name, $productId]);

                set_flash('success', 'Product "' . $name . '" was updated.');
            } else {
                $statement = $pdo->prepare(
                    'INSERT INTO products (name, category, description, unit_price, stock, is_available)
                     VALUES (?, ?, ?, ?, ?, ?)'
                );
                $statement->execute([$name, $category, $description, $unitPrice, (int) $stockRaw, $isAvailable]);
                set_flash('success', 'Product "' . $name . '" was added to the catalogue.');
            }
        } catch (Throwable $e) {
            error_log('Product save failed: ' . $e->getMessage());
            set_flash('error', 'Unable to save that product. Please try again.');
        }

        redirect('products.php');
    }

    redirect('products.php');
}


$q      = trim((string) ($_GET['q'] ?? ''));
$editId = parse_positive_int($_GET['edit'] ?? null);

$formState = take_form_state();
$errors    = $formState['errors'];

$editProduct = null;
if ($editId !== null) {
    $statement = $pdo->prepare('SELECT * FROM products WHERE id = ? LIMIT 1');
    $statement->execute([$editId]);
    $editProduct = $statement->fetch();

    if (!$editProduct) {
        set_flash('error', 'That product could not be found.');
        redirect('products.php');
    }
}

$defaults = [
    'name'         => '',
    'category'     => 'General',
    'description'  => '',
    'unit_price'   => '',
    'stock'        => '0',
    'is_available' => 1,
];

if ($formState['old']) {
    $form = array_merge($defaults, $formState['old']);
    $editId = parse_positive_int($_POST['id'] ?? null) ?? $editId;
} elseif ($editProduct) {
    $form = [
        'name'         => $editProduct['name'],
        'category'     => $editProduct['category'],
        'description'  => $editProduct['description'] ?? '',
        'unit_price'   => number_format((float) $editProduct['unit_price'], 2, '.', ''),
        'stock'        => (string) $editProduct['stock'],
        'is_available' => (int) $editProduct['is_available'],
    ];
} else {
    $form = $defaults;
}

$sql    = 'SELECT id, name, category, description, unit_price, stock, is_available, created_at FROM products';
$params = [];

if ($q !== '') {
    $sql .= ' WHERE name LIKE :q1 OR category LIKE :q2 OR description LIKE :q3';
    $like = '%' . $q . '%';
    $params = [':q1' => $like, ':q2' => $like, ':q3' => $like];
}

$sql .= ' ORDER BY is_available DESC, name ASC';

$statement = $pdo->prepare($sql);
$statement->execute($params);
$products = $statement->fetchAll();

$totalCount     = (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
$availableCount = (int) $pdo->query('SELECT COUNT(*) FROM products WHERE is_available = 1')->fetchColumn();
$outOfStock     = (int) $pdo->query('SELECT COUNT(*) FROM products WHERE stock = 0')->fetchColumn();
$isEditing      = $editId !== null && $editId > 0;

$pageTitle    = 'Products';
$pageSubtitle = 'Manage your catalogue, prices, stock levels and availability.';
$activePage   = 'products';

require __DIR__ . '/../includes/header.php';
?>

<?php if ($errors): ?>
    <div class="flash flash-error" role="alert">
        <span class="flash-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16.5v.5"/></svg>
        </span>
        <span>Please fix the highlighted product fields.</span>
    </div>
<?php endif; ?>

<section class="stats-grid stats-grid-3">
    <article class="stat-card stat-card-sm">
        <div class="stat-meta">
            <span>Total products</span>
            <strong><?= number_format($totalCount) ?></strong>
            <small>Catalogue size</small>
        </div>
    </article>
    <article class="stat-card stat-card-sm">
        <div class="stat-meta">
            <span>Available</span>
            <strong><?= number_format($availableCount) ?></strong>
            <small>Visible on the ordering page</small>
        </div>
    </article>
    <article class="stat-card stat-card-sm">
        <div class="stat-meta">
            <span>Out of stock</span>
            <strong><?= number_format($outOfStock) ?></strong>
            <small>Needs restocking</small>
        </div>
    </article>
</section>

<div class="products-layout">
    <section class="panel">
        <div class="panel-head panel-head-wrap">
            <div>
                <h3>Product catalogue</h3>
                <p><?= count($products) ?> <?= count($products) === 1 ? 'product' : 'products' ?><?= $q !== '' ? ' matching your search' : '' ?>.</p>
            </div>
            <a class="btn btn-primary btn-sm" href="products.php#productForm">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                Add product
            </a>
        </div>

        <div class="toolbar toolbar-inside">
            <form class="filter-form" method="get" action="products.php" role="search">
                <div class="search-form">
                    <span class="search-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                    </span>
                    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search by product name, category or description…" aria-label="Search products">
                </div>
                <button class="btn btn-primary btn-sm" type="submit">Search</button>
                <?php if ($q !== ''): ?>
                    <a class="text-link" href="products.php">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <?php if (!$products): ?>
            <div class="empty-state empty-state-lg">
                <span class="empty-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="m3 8 9 5 9-5M12 13v8"/></svg>
                </span>
                <h4><?= $q !== '' ? 'No matching products' : 'No products yet' ?></h4>
                <p><?= $q !== '' ? 'Try a different search term.' : 'Add your first product to start taking orders.' ?></p>
                <a class="btn btn-primary btn-sm" href="<?= $q !== '' ? 'products.php' : 'products.php#productForm' ?>">
                    <?= $q !== '' ? 'Clear search' : 'Add a product' ?>
                </a>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th class="num">Unit Price</th>
                            <th class="num">Stock</th>
                            <th>Availability</th>
                            <th class="actions-col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                            <?php
                            $stock = (int) $product['stock'];
                            $stockClass = $stock === 0 ? 'stock-out' : ($stock <= 5 ? 'stock-low' : 'stock-ok');
                            ?>
                            <tr>
                                <td data-label="Product">
                                    <div class="product-cell">
                                        <span class="product-avatar"><?= e(mb_substr($product['name'], 0, 1)) ?></span>
                                        <span>
                                            <strong class="cell-strong"><?= e($product['name']) ?></strong>
                                            <small class="muted"><?= e($product['category']) ?><?= $product['description'] ? ' · ' . e($product['description']) : '' ?></small>
                                        </span>
                                    </div>
                                </td>
                                <td data-label="Unit Price" class="num"><?= format_money($product['unit_price']) ?></td>
                                <td data-label="Stock" class="num"><span class="<?= $stockClass ?>"><?= $stock ?></span></td>
                                <td data-label="Availability">
                                    <?php if ((int) $product['is_available'] === 1): ?>
                                        <span class="badge badge-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-muted">Hidden</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Actions" class="actions-col">
                                    <div class="row-actions">
                                        <a class="icon-btn" href="products.php?edit=<?= (int) $product['id'] ?>#productForm" title="Edit product" aria-label="Edit <?= e($product['name']) ?>">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
                                        </a>
                                        <form method="post" action="products.php" data-confirm="Delete &quot;<?= e($product['name']) ?>&quot;? Existing orders will keep their saved product details.">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
                                            <button class="icon-btn icon-btn-danger" type="submit" title="Delete product" aria-label="Delete <?= e($product['name']) ?>">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6M10 11v5M14 11v5"/></svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <aside class="panel product-form-panel" id="productForm">
        <div class="panel-head">
            <div>
                <h3><?= $isEditing ? 'Edit product' : 'Add a product' ?></h3>
                <p><?= $isEditing ? 'Update the details below and save your changes.' : 'New products appear on the ordering page immediately.' ?></p>
            </div>
            <?php if ($isEditing): ?>
                <a class="text-link" href="products.php#productForm">Cancel edit</a>
            <?php endif; ?>
        </div>

        <form method="post" action="products.php" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= $isEditing ? (int) $editId : '' ?>">

            <div class="field">
                <label for="name">Product name <span class="req">*</span></label>
                <input type="text" id="name" name="name" value="<?= e($form['name']) ?>" maxlength="120"
                       placeholder="e.g. Wireless Mouse"
                       class="<?= isset($errors['name']) ? 'is-invalid' : '' ?>">
                <?php if (isset($errors['name'])): ?><span class="field-error"><?= e($errors['name']) ?></span><?php endif; ?>
            </div>

            <div class="field">
                <label for="category">Category <span class="req">*</span></label>
                <input type="text" id="category" name="category" value="<?= e($form['category']) ?>" maxlength="60"
                       list="categoryOptions" placeholder="e.g. Electronics"
                       class="<?= isset($errors['category']) ? 'is-invalid' : '' ?>">
                <datalist id="categoryOptions">
                    <?php foreach (['Electronics', 'Audio', 'Accessories', 'Wearables', 'Storage', 'General'] as $category): ?>
                        <option value="<?= e($category) ?>"></option>
                    <?php endforeach; ?>
                </datalist>
                <?php if (isset($errors['category'])): ?><span class="field-error"><?= e($errors['category']) ?></span><?php endif; ?>
            </div>

            <div class="field">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="3" maxlength="255"
                          placeholder="Short description shown to customers"
                          class="<?= isset($errors['description']) ? 'is-invalid' : '' ?>"><?= e($form['description']) ?></textarea>
                <?php if (isset($errors['description'])): ?><span class="field-error"><?= e($errors['description']) ?></span><?php endif; ?>
            </div>

            <div class="form-grid form-grid-tight">
                <div class="field">
                    <label for="unit_price">Unit price (<?= e(CURRENCY) ?>) <span class="req">*</span></label>
                    <input type="text" id="unit_price" name="unit_price" value="<?= e($form['unit_price']) ?>"
                           inputmode="decimal" placeholder="0.00"
                           class="<?= isset($errors['unit_price']) ? 'is-invalid' : '' ?>">
                    <?php if (isset($errors['unit_price'])): ?><span class="field-error"><?= e($errors['unit_price']) ?></span><?php endif; ?>
                </div>

                <div class="field">
                    <label for="stock">Stock <span class="req">*</span></label>
                    <input type="number" id="stock" name="stock" value="<?= e($form['stock']) ?>" min="0" step="1"
                           class="<?= isset($errors['stock']) ? 'is-invalid' : '' ?>">
                    <?php if (isset($errors['stock'])): ?><span class="field-error"><?= e($errors['stock']) ?></span><?php endif; ?>
                </div>
            </div>

            <label class="switch-row">
                <input type="checkbox" name="is_available" value="1" <?= (int) $form['is_available'] === 1 ? 'checked' : '' ?>>
                <span class="switch-ui"></span>
                <span>
                    <strong>Available for ordering</strong>
                    <small>Uncheck to hide this product from the ordering page.</small>
                </span>
            </label>

            <div class="form-actions">
                <button class="btn btn-primary btn-block" type="submit"><?= $isEditing ? 'Save changes' : 'Add product' ?></button>
                <?php if ($isEditing): ?>
                    <a class="btn btn-ghost btn-block" href="products.php">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </aside>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
