<?php
declare(strict_types=1);


function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

/**
 * Build a URL that works from the project root and from the owner/ and
 * customer/ sub-directories, whether the app lives at the web root or in a
 * sub-folder such as /product-ordering.
 */
function app_url(string $path = ''): string
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    $dir    = rtrim(str_replace('\\', '/', dirname($script)), '/');

    if (in_array(basename($dir), ['owner', 'customer'], true)) {
        $dir = rtrim(str_replace('\\', '/', dirname($dir)), '/');
    }

    $base = $dir === '' ? '/' : $dir . '/';

    return $base . ltrim($path, '/');
}

function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

function current_role(): string
{
    $role = (string) ($_SESSION['role'] ?? '');

    return in_array($role, ['owner', 'customer'], true) ? $role : 'customer';
}

function current_user(): array
{
    return [
        'id'       => (int) ($_SESSION['user_id'] ?? 0),
        'name'     => (string) ($_SESSION['user_name'] ?? 'Guest'),
        'username' => (string) ($_SESSION['username'] ?? ''),
        'role'     => current_role(),
    ];
}

function is_owner(): bool
{
    return is_logged_in() && current_role() === 'owner';
}

function is_customer(): bool
{
    return is_logged_in() && current_role() === 'customer';
}

function home_path_for(string $role): string
{
    return $role === 'owner' ? 'owner/dashboard.php' : 'customer/order.php';
}

function home_url(): string
{
    return app_url(home_path_for(current_role()));
}

function require_login(): void
{
    if (!is_logged_in()) {
        set_flash('error', 'Please sign in to continue.');
        redirect(app_url('login.php'));
    }
}

function require_role(string $role): void
{
    require_login();

    if (current_role() === $role) {
        return;
    }

    set_flash(
        'error',
        $role === 'owner'
            ? 'The owner workspace is only available to store owner accounts.'
            : 'The customer portal is only available to customer accounts.'
    );

    redirect(home_url());
}


function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        exit('Your session expired. Please go back, refresh the page and try again.');
    }
}


function set_flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);

    return $flashes;
}


function set_form_state(array $errors, array $old): void
{
    $_SESSION['form_state'] = ['errors' => $errors, 'old' => $old];
}

function take_form_state(): array
{
    $state = $_SESSION['form_state'] ?? ['errors' => [], 'old' => []];
    unset($_SESSION['form_state']);

    return $state;
}


function format_money(float|int|string $amount): string
{
    return CURRENCY . number_format((float) $amount, 2);
}

function order_code(int|string $id): string
{
    return 'ORD-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT);
}

function format_date(?string $datetime, string $format = 'M j, Y g:i A'): string
{
    if (!$datetime) {
        return '—';
    }

    $timestamp = strtotime($datetime);

    return $timestamp ? date($format, $timestamp) : '—';
}

function status_class(string $status): string
{
    return match ($status) {
        'Completed' => 'badge-success',
        'Cancelled' => 'badge-danger',
        default     => 'badge-warning',
    };
}


function request_string(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $_GET[$key] ?? $default;

    return is_string($value) ? trim($value) : $default;
}


function parse_positive_int(mixed $value): ?int
{
    if (is_int($value)) {
        return $value > 0 ? $value : null;
    }

    if (!is_string($value) || !preg_match('/^\d+$/', $value)) {
        return null;
    }

    $number = (int) $value;

    return $number > 0 ? $number : null;
}


function parse_money(mixed $value): ?float
{
    if (!is_string($value) && !is_int($value) && !is_float($value)) {
        return null;
    }

    $value = trim((string) $value);

    if (!preg_match('/^\d{1,7}(\.\d{1,2})?$/', $value)) {
        return null;
    }

    return (float) $value;
}


function customer_email_error(string $email): ?string
{
    if ($email === '') {
        return 'Customer email is required.';
    }

    if (mb_strlen($email) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'Enter a valid customer email address.';
    }

    return null;
}

function customer_phone_error(string $phone): ?string
{
    if ($phone === '') {
        return 'Customer phone number is required.';
    }

    $digits = preg_replace('/\D/', '', $phone);

    if (
        mb_strlen($phone) > 30
        || !preg_match('/^\+?[0-9(][0-9\s().-]{5,24}$/', $phone)
        || strlen($digits) < 7
        || strlen($digits) > 15
    ) {
        return 'Enter a valid phone number with 7 to 15 digits.';
    }

    return null;
}

function customer_address_error(string $address): ?string
{
    if ($address === '') {
        return 'Delivery address is required.';
    }

    $length = mb_strlen($address);

    if ($length < 5 || $length > 255) {
        return 'Delivery address must be between 5 and 255 characters.';
    }

    return null;
}

function payment_method_error(string $method): ?string
{
    return in_array($method, PAYMENT_METHODS, true) ? null : 'Choose a valid payment method.';
}
