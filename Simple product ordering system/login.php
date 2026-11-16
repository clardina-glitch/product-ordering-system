<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) {
    redirect(home_url());
}

$errors = [];
$loginUsername = '';
$registerName = '';
$registerUsername = '';
$signedOut = isset($_GET['signed_out']);
$activeTab = (($_GET['tab'] ?? '') === 'register') ? 'register' : 'login';
$flashes   = get_flashes();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $authAction = request_string('auth_action');

    if ($authAction === 'register') {
        $activeTab = 'register';

        $registerName = request_string('full_name');
        $registerUsername = request_string('username');
        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';

        if ($registerName === '') {
            $errors['full_name'] = 'Full name is required.';
        } elseif (mb_strlen($registerName) < 2 || mb_strlen($registerName) > 100) {
            $errors['full_name'] = 'Full name must be between 2 and 100 characters.';
        } elseif (!preg_match("/^[\p{L}][\p{L}\p{M}\s.'-]*$/u", $registerName)) {
            $errors['full_name'] = 'Full name may only contain letters, spaces, periods, apostrophes and hyphens.';
        }

        if ($registerUsername === '') {
            $errors['username'] = 'Username is required.';
        } elseif (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $registerUsername)) {
            $errors['username'] = 'Username must be 3 to 50 characters using letters, numbers, dots, underscores or hyphens.';
        }

        if (!is_string($password) || $password === '') {
            $errors['password'] = 'Password is required.';
        } elseif (strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters.';
        } elseif (strlen($password) > 72) {
            $errors['password'] = 'Password must be 72 characters or fewer.';
        }

        if (!is_string($passwordConfirm) || $passwordConfirm === '') {
            $errors['password_confirm'] = 'Please confirm your password.';
        } elseif (is_string($password) && $password !== '' && $passwordConfirm !== $password) {
            $errors['password_confirm'] = 'Passwords do not match.';
        }

        if (!isset($errors['username'])) {
            $statement = db()->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
            $statement->execute([$registerUsername]);

            if ($statement->fetch()) {
                $errors['username'] = 'That username is already taken.';
            }
        }

        if (!$errors) {
            try {
                $statement = db()->prepare(
                    "INSERT INTO users (username, password_hash, full_name, role) VALUES (?, ?, ?, 'customer')"
                );
                $statement->execute([
                    $registerUsername,
                    password_hash($password, PASSWORD_DEFAULT),
                    $registerName,
                ]);

                $userId = (int) db()->lastInsertId();

                session_regenerate_id(true);
                $_SESSION['user_id'] = $userId;
                $_SESSION['user_name'] = $registerName;
                $_SESSION['username'] = $registerUsername;
                $_SESSION['role'] = 'customer';

                set_flash('success', 'Welcome to OrderDesk, ' . $registerName . '. Your customer account is ready.');
                redirect(home_url());
            } catch (PDOException $e) {
                if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                    $errors['username'] = 'That username is already taken.';
                } else {
                    error_log('Registration failed: ' . $e->getMessage());
                    $errors['register'] = 'Unable to create your account right now. Please try again.';
                }
            }
        }
    } else {
        $activeTab = 'login';

        $loginUsername = request_string('username');
        $password = $_POST['password'] ?? '';

        if ($loginUsername === '') {
            $errors['username'] = 'Username is required.';
        } elseif (strlen($loginUsername) > 50) {
            $errors['username'] = 'Username is too long.';
        }

        if (!is_string($password) || $password === '') {
            $errors['password'] = 'Password is required.';
        }

        if (!$errors) {
            $statement = db()->prepare('SELECT id, username, password_hash, full_name, role FROM users WHERE username = ? LIMIT 1');
            $statement->execute([$loginUsername]);
            $user = $statement->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                $role = in_array($user['role'], ['owner', 'customer'], true) ? $user['role'] : 'customer';

                session_regenerate_id(true);
                $_SESSION['user_id'] = (int) $user['id'];
                $_SESSION['user_name'] = $user['full_name'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $role;

                set_flash('success', 'Welcome back, ' . $user['full_name'] . '.');
                redirect(home_url());
            }

            $errors['login'] = 'Incorrect username or password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $activeTab === 'register' ? 'Create account' : 'Sign in' ?> · OrderDesk</title>
    <link rel="icon" href="<?= e(app_url('assets/favicon.svg')) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e(app_url('assets/css/style.css')) ?>">
</head>
<body class="auth-body">
    <div class="auth-shell">
        <section class="auth-visual">
            <div class="auth-visual-inner">
                <a class="brand brand-light" href="login.php">
                    <span class="brand-mark">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
                            <path d="M3 6h18M16 10a4 4 0 0 1-8 0"/>
                        </svg>
                    </span>
                    <span class="brand-text">
                        <strong>OrderDesk</strong>
                        <small>Product ordering</small>
                    </span>
                </a>

                <div class="auth-copy">
                    <span class="eyebrow">Simple Product Ordering System</span>
                    <h1>Take orders.<br>Keep things simple.</h1>
                    <p>Browse your catalogue, build an order with a live total, and keep every customer order organised in one place.</p>
                </div>

                <ul class="auth-points">
                    <li><span>01</span> Customers browse products and place orders</li>
                    <li><span>02</span> Select quantities with an instant order summary</li>
                    <li><span>03</span> Owners manage products and every customer order</li>
                </ul>
            </div>
        </section>

        <section class="auth-panel">
            <div class="auth-card">
                <div class="auth-card-head">
                    <span class="eyebrow"><?= $activeTab === 'register' ? 'Get started' : 'Welcome back' ?></span>
                    <h2><?= $activeTab === 'register' ? 'Create your account' : 'Sign in to your account' ?></h2>
                    <p><?= $activeTab === 'register' ? 'Create a customer account to place and track your orders.' : 'Sign in as a customer or as the store owner.' ?></p>
                </div>

                <?php if ($flashes): ?>
                    <div class="flash-stack">
                        <?php foreach ($flashes as $flash): ?>
                            <div class="flash flash-<?= e($flash['type']) ?>" role="status">
                                <span class="flash-icon">
                                    <?php if ($flash['type'] === 'success'): ?>
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="m5 13 4 4L19 7"/></svg>
                                    <?php else: ?>
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16.5v.5"/></svg>
                                    <?php endif; ?>
                                </span>
                                <span><?= e($flash['message']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <nav class="auth-tabs" aria-label="Account access">
                    <a class="auth-tab <?= $activeTab === 'login' ? 'is-active' : '' ?>" href="login.php"
                       <?= $activeTab === 'login' ? 'aria-current="page"' : '' ?>>Sign in</a>
                    <a class="auth-tab <?= $activeTab === 'register' ? 'is-active' : '' ?>" href="login.php?tab=register"
                       <?= $activeTab === 'register' ? 'aria-current="page"' : '' ?>>Create account</a>
                </nav>

                <div class="auth-pane" <?= $activeTab !== 'login' ? 'hidden' : '' ?>>
                    <?php if ($signedOut): ?>
                        <div class="flash flash-info" role="status">
                            <span class="flash-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16.5v.5"/></svg>
                            </span>
                            <span>You have been signed out.</span>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($errors['login'])): ?>
                        <div class="flash flash-error" role="alert">
                            <span class="flash-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16.5v.5"/></svg>
                            </span>
                            <span><?= e($errors['login']) ?></span>
                        </div>
                    <?php endif; ?>

                    <form method="post" action="login.php" novalidate>
                        <?= csrf_field() ?>
                        <input type="hidden" name="auth_action" value="login">

                        <div class="field">
                            <label for="login_username">Username</label>
                            <input type="text" id="login_username" name="username" value="<?= e($loginUsername) ?>"
                                   placeholder="Enter your username" autocomplete="username"
                                   <?= $activeTab === 'login' ? 'autofocus' : '' ?>
                                   class="<?= isset($errors['username']) ? 'is-invalid' : '' ?>">
                            <?php if (isset($errors['username'])): ?>
                                <span class="field-error"><?= e($errors['username']) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="field">
                            <label for="login_password">Password</label>
                            <div class="password-wrap">
                                <input type="password" id="login_password" name="password"
                                       placeholder="Enter your password" autocomplete="current-password"
                                       class="<?= isset($errors['password']) ? 'is-invalid' : '' ?>">
                                <button type="button" class="password-toggle" data-target="login_password" aria-label="Show password">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6z"/><circle cx="12" cy="12" r="2.5"/>
                                    </svg>
                                </button>
                            </div>
                            <?php if (isset($errors['password'])): ?>
                                <span class="field-error"><?= e($errors['password']) ?></span>
                            <?php endif; ?>
                        </div>

                        <button class="btn btn-primary btn-block" type="submit">Sign in</button>
                    </form>
                </div>

                <div class="auth-pane" <?= $activeTab !== 'register' ? 'hidden' : '' ?>>
                    <?php if (isset($errors['register'])): ?>
                        <div class="flash flash-error" role="alert">
                            <span class="flash-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16.5v.5"/></svg>
                            </span>
                            <span><?= e($errors['register']) ?></span>
                        </div>
                    <?php elseif ($errors): ?>
                        <div class="flash flash-error" role="alert">
                            <span class="flash-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16.5v.5"/></svg>
                            </span>
                            <span>Please fix the highlighted fields below.</span>
                        </div>
                    <?php endif; ?>

                    <form method="post" action="login.php?tab=register" novalidate>
                        <?= csrf_field() ?>
                        <input type="hidden" name="auth_action" value="register">

                        <div class="field">
                            <label for="full_name">Full name</label>
                            <input type="text" id="full_name" name="full_name" value="<?= e($registerName) ?>"
                                   placeholder="e.g. Maria Santos" autocomplete="name" maxlength="100"
                                   <?= $activeTab === 'register' ? 'autofocus' : '' ?>
                                   class="<?= isset($errors['full_name']) ? 'is-invalid' : '' ?>">
                            <?php if (isset($errors['full_name'])): ?>
                                <span class="field-error"><?= e($errors['full_name']) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="field">
                            <label for="register_username">Username</label>
                            <input type="text" id="register_username" name="username" value="<?= e($registerUsername) ?>"
                                   placeholder="Choose a username" autocomplete="username" maxlength="50"
                                   class="<?= isset($errors['username']) ? 'is-invalid' : '' ?>">
                            <?php if (isset($errors['username'])): ?>
                                <span class="field-error"><?= e($errors['username']) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="field">
                            <label for="register_password">Password</label>
                            <div class="password-wrap">
                                <input type="password" id="register_password" name="password"
                                       placeholder="At least 8 characters" autocomplete="new-password"
                                       class="<?= isset($errors['password']) ? 'is-invalid' : '' ?>">
                                <button type="button" class="password-toggle" data-target="register_password" aria-label="Show password">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6z"/><circle cx="12" cy="12" r="2.5"/>
                                    </svg>
                                </button>
                            </div>
                            <?php if (isset($errors['password'])): ?>
                                <span class="field-error"><?= e($errors['password']) ?></span>
                            <?php else: ?>
                                <span class="field-hint">Use 8 or more characters.</span>
                            <?php endif; ?>
                        </div>

                        <div class="field">
                            <label for="register_password_confirm">Confirm password</label>
                            <div class="password-wrap">
                                <input type="password" id="register_password_confirm" name="password_confirm"
                                       placeholder="Repeat your password" autocomplete="new-password"
                                       class="<?= isset($errors['password_confirm']) ? 'is-invalid' : '' ?>">
                                <button type="button" class="password-toggle" data-target="register_password_confirm" aria-label="Show password">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6z"/><circle cx="12" cy="12" r="2.5"/>
                                    </svg>
                                </button>
                            </div>
                            <?php if (isset($errors['password_confirm'])): ?>
                                <span class="field-error"><?= e($errors['password_confirm']) ?></span>
                            <?php endif; ?>
                        </div>

                        <button class="btn btn-primary btn-block" type="submit">Create account</button>
                    </form>
                </div>
            </div>

            <p class="auth-footer">OrderDesk · Simple Product Ordering System</p>
        </section>
    </div>
    <script src="<?= e(app_url('assets/js/app.js')) ?>"></script>
</body>
</html>
