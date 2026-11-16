<?php
require_once __DIR__ . '/db.php';

if (isset($_GET['logout'])) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    session_start();
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    flash('success', 'You have been safely logged out.');
    redirect('index.php');
}

$error = '';
$submittedLogin = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $submittedLogin = trim((string) ($_POST['username'] ?? $_POST['user_id'] ?? ''));
    $submittedPassword = (string) ($_POST['password'] ?? '');

    if (!preg_match('/^[A-Za-z0-9_.-]{3,30}$/', $submittedLogin) || $submittedPassword === '') {
        $error = 'Invalid User Credentials';
    } else {
        $loginValue = mysqli_real_escape_string($conn, $submittedLogin);
        $sql = "SELECT user_id, username, password_hash FROM `USER` WHERE username = '$loginValue' OR user_id = '$loginValue'";
        $result = mysqli_query($conn, $sql);
        if (!$result) die('Login query failed: ' . mysqli_error($conn));
        if (mysqli_num_rows($result) === 0) {
            $error = 'Invalid User Credentials';
        } else {
            $userRow = mysqli_fetch_array($result, MYSQLI_ASSOC);
            $submittedId = clean_id((string) $userRow['user_id']);
            if (!password_verify($submittedPassword, (string) $userRow['password_hash'])) {
                $error = 'Invalid User Credentials';
            } else {
                $safeUserId = mysqli_real_escape_string($conn, $submittedId);
                $sql = "SELECT EXISTS (SELECT 1 FROM PATIENT WHERE user_id = '$safeUserId') AS role_exists";
                $result = mysqli_query($conn, $sql);
                if (!$result) die('Patient role query failed: ' . mysqli_error($conn));
                $roleRow = mysqli_fetch_array($result, MYSQLI_ASSOC);
                if ((int) $roleRow['role_exists'] === 1) {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $submittedId;
                    $_SESSION['username'] = (string) $userRow['username'];
                    $_SESSION['role'] = 'patient';
                    redirect('patient.php?user_id=' . rawurlencode($submittedId));
                }

                $sql = "SELECT EXISTS (SELECT 1 FROM DRIVER WHERE user_id = '$safeUserId') AS role_exists";
                $result = mysqli_query($conn, $sql);
                if (!$result) die('Driver role query failed: ' . mysqli_error($conn));
                $roleRow = mysqli_fetch_array($result, MYSQLI_ASSOC);
                if ((int) $roleRow['role_exists'] === 1) {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $submittedId;
                    $_SESSION['username'] = (string) $userRow['username'];
                    $_SESSION['role'] = 'driver';
                    redirect('driver.php?user_id=' . rawurlencode($submittedId));
                }

                $sql = "SELECT EXISTS (SELECT 1 FROM ADMIN WHERE user_id = '$safeUserId') AS role_exists";
                $result = mysqli_query($conn, $sql);
                if (!$result) die('Admin role query failed: ' . mysqli_error($conn));
                $roleRow = mysqli_fetch_array($result, MYSQLI_ASSOC);
                if ((int) $roleRow['role_exists'] === 1) {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $submittedId;
                    $_SESSION['username'] = (string) $userRow['username'];
                    $_SESSION['role'] = 'admin';
                    redirect('admin.php?user_id=' . rawurlencode($submittedId));
                }

                $error = 'Invalid User Credentials';
            }
        }
    }
}

layout_header('Sign in', '');
?>
<section class="auth-shell">
    <div class="auth-card">
        <span class="kicker">24 / 7 DISPATCH GRID</span>
        <h1>Move care<br>faster.</h1>
        <p class="lede">A high-contrast dispatch console for patients, drivers, and fleet administrators.</p>
        <?php if ($error !== ''): ?><div class="alert error" role="alert"><?= e($error) ?></div><?php endif; ?>
        <form method="post" novalidate>
            <?= csrf_field() ?>
            <label for="username">Username</label>
            <input id="username" name="username" value="<?= e($submittedLogin) ?>" placeholder="your_unique_username" maxlength="30" autocomplete="username" required>
            <label for="password">Password</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required>
            <div class="form-actions"><button class="btn" type="submit">Enter dispatch desk ↗</button></div>
        </form>
        <div class="auth-links"><a class="btn teal" href="signup.php">Create a patient, driver, or admin account ↗</a></div>
    </div>
</section>
<?php layout_footer(); ?>
