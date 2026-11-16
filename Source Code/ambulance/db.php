<?php
ob_start();
require_once __DIR__ . '/DBconnect.php';
ob_end_clean();
if (!mysqli_select_db($conn, 'uber_for_ambulance')) {
    die('Database selection failed: ' . mysqli_error($conn));
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e($_SESSION['csrf_token']) . '">';
}

function require_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(419);
        exit('Invalid form token. Please go back and try again.');
    }
}

function clean_id(string $value): string
{
    return strtoupper(trim($value));
}

function redirect(string $location): never
{
    header('Location: ' . $location);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

function require_role(string $role): string
{
    $userId = clean_id((string) ($_SESSION['user_id'] ?? ''));
    if ($userId === '' || ($_SESSION['role'] ?? '') !== $role) {
        redirect('index.php');
    }

    return $userId;
}

function phone_contact(?string $phone): string
{
    if (!$phone) return '<span class="mono">No phone on file</span>';
    return '<span class="phone-contact"><span class="mono">' . e($phone) . '</span></span>';
}

function layout_header(string $title, string $eyebrow, string $userId = ''): void
{
    $flash = get_flash();
    $dashboardByRole = [
        'patient' => 'patient.php',
        'driver' => 'driver.php',
        'admin' => 'admin.php',
    ];
    $role = (string) ($_SESSION['role'] ?? '');
    $displayUsername = (string) ($_SESSION['username'] ?? '');
    $brandHref = 'index.php';
    if ($userId !== '' && isset($dashboardByRole[$role])) {
        $brandHref = $dashboardByRole[$role] . '?user_id=' . rawurlencode($userId);
    }
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= e($title) ?> · Uber for Ambulance</title>
        <link rel="stylesheet" href="style.css">
    </head>
    <body>
    <header class="site-header">
        <div class="container navbar">
            <a class="brand" href="<?= e($brandHref) ?>">
                <span class="brand-mark">+</span><span>Uber for Ambulance</span>
            </a>
            <nav class="nav-links">
                <?php if ($userId !== ''): ?><span class="mono">@<?= e($displayUsername !== '' ? $displayUsername : $userId) ?></span><a href="index.php?logout=1">Log out</a><?php endif; ?>
            </nav>
        </div>
    </header>
    <main>
        <div class="container">
            <?php if ($flash): ?><div class="alert <?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div><?php endif; ?>
            <?php if ($eyebrow !== ''): ?><p class="eyebrow"><?= e($eyebrow) ?></p><?php endif; ?>
    <?php
}

function layout_footer(): void
{
    ?>
        </div>
    </main>
    </body>
    </html>
    <?php
}
