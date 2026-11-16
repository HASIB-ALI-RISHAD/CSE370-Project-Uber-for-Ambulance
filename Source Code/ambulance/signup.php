<?php
require_once __DIR__ . '/db.php';

$error = '';
$old = ['role' => 'patient', 'username' => '', 'first_name' => '', 'last_name' => '', 'phone' => '', 'password' => '', 'emergency_type' => '', 'age' => '', 'license_no' => '', 'admin_level' => '', 'office_room_no' => ''];
$adminLevels = ['Fleet Supervisor', 'Operations Manager'];
$officeRooms = ['ER-01', 'ER-02', 'ER-07', 'ER-09'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    foreach ($old as $key => $value) $old[$key] = trim((string) ($_POST[$key] ?? $value));
    $role = $old['role'];
    $errors = [];
    if (!in_array($role, ['patient', 'driver', 'admin'], true)) $errors[] = 'Choose a supported account type.';
    $username = strtolower($old['username']);
    if (!preg_match('/^[a-z0-9][a-z0-9_.-]{2,29}$/', $username)) $errors[] = 'Username must be 3 to 30 characters using letters, numbers, dot, underscore, or hyphen.';
    if ($old['first_name'] === '' || !preg_match('/^[\p{L} .\'-]{2,50}$/u', $old['first_name'])) $errors[] = 'Enter a valid first name.';
    if ($old['last_name'] === '' || !preg_match('/^[\p{L} .\'-]{2,50}$/u', $old['last_name'])) $errors[] = 'Enter a valid last name.';
    if ($old['phone'] === '' || !preg_match('/^[+0-9 ()-]{7,20}$/', $old['phone'])) $errors[] = 'Enter a valid phone number.';
    if (mb_strlen($old['password']) < 8 || mb_strlen($old['password']) > 72) $errors[] = 'Password must be between 8 and 72 characters.';
    if ($role === 'patient') {
        $age = filter_var($old['age'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 120]]);
        if ($age === false) $errors[] = 'Enter an age from 0 to 120.';
        if ($old['emergency_type'] === '' || mb_strlen($old['emergency_type']) > 50) $errors[] = 'Enter an emergency type up to 50 characters.';
    } elseif ($role === 'driver') {
        if ($old['license_no'] === '' || !preg_match('/^[A-Za-z0-9-]{3,20}$/', $old['license_no'])) $errors[] = 'Enter a valid license number.';
    } else {
        if (!in_array($old['admin_level'], $adminLevels, true)) $errors[] = 'Choose a valid admin level.';
        if (!in_array($old['office_room_no'], $officeRooms, true)) $errors[] = 'Choose a valid office room.';
    }

    if (!$errors) {
        $safeUsername = mysqli_real_escape_string($conn, $username);
        $sql = "SELECT EXISTS (SELECT 1 FROM `USER` WHERE username = '$safeUsername') AS username_exists";
        $result = mysqli_query($conn, $sql);
        if (!$result) die('Username check failed: ' . mysqli_error($conn));
        $usernameRow = mysqli_fetch_array($result, MYSQLI_ASSOC);
        if ((int) $usernameRow['username_exists'] === 1) {
            $error = 'That username is already taken. Please choose another one.';
        }
    }

    if (!$errors && $error === '') {
        $prefix = $role === 'patient' ? 'PT-' : ($role === 'driver' ? 'DR-' : 'AD-');
        if (!mysqli_begin_transaction($conn)) {
            $error = 'The registration transaction could not start.';
        } else {
            do {
                $userId = $prefix . random_int(100, 999);
                $safeUserId = mysqli_real_escape_string($conn, $userId);
                $sql = "SELECT EXISTS (SELECT 1 FROM `USER` WHERE user_id = '$safeUserId') AS user_exists";
                $result = mysqli_query($conn, $sql);
                if (!$result) die('User ID check failed: ' . mysqli_error($conn));
                $idRow = mysqli_fetch_array($result, MYSQLI_ASSOC);
            } while ((int) $idRow['user_exists'] === 1);

            $safeFirstName = mysqli_real_escape_string($conn, $old['first_name']);
            $safeLastName = mysqli_real_escape_string($conn, $old['last_name']);
            $passwordHash = mysqli_real_escape_string($conn, password_hash($old['password'], PASSWORD_DEFAULT));
            $sql = "INSERT INTO `USER` (user_id, username, first_name, last_name, password_hash) VALUES ('$safeUserId', '$safeUsername', '$safeFirstName', '$safeLastName', '$passwordHash')";
            $userResult = mysqli_query($conn, $sql);
            if (!$userResult || mysqli_affected_rows($conn) !== 1) {
                $error = 'The user account could not be created.';
            } elseif ($role === 'patient') {
                $safeEmergencyType = mysqli_real_escape_string($conn, $old['emergency_type']);
                $sql = "INSERT INTO PATIENT (user_id, emergency_type, age) VALUES ('$safeUserId', '$safeEmergencyType', $age)";
                $subtypeResult = mysqli_query($conn, $sql);
                if (!$subtypeResult || mysqli_affected_rows($conn) !== 1) $error = 'The patient details could not be saved.';
            } elseif ($role === 'driver') {
                $sql = 'SELECT a.user_id FROM ADMIN a LEFT JOIN DRIVER d ON d.admin_id = a.user_id GROUP BY a.user_id ORDER BY COUNT(d.user_id), a.user_id LIMIT 1';
                $result = mysqli_query($conn, $sql);
                if (!$result) die('Driver administrator query failed: ' . mysqli_error($conn));
                $driverAdminId = false;
                if (mysqli_num_rows($result) > 0) {
                    $driverAdminRow = mysqli_fetch_array($result, MYSQLI_ASSOC);
                    $driverAdminId = $driverAdminRow['user_id'];
                }
                if (!$driverAdminId) {
                    $error = 'No administrator is available to register this driver.';
                } else {
                    $safeLicense = mysqli_real_escape_string($conn, strtoupper($old['license_no']));
                    $safeDriverAdminId = mysqli_real_escape_string($conn, $driverAdminId);
                    $sql = "INSERT INTO DRIVER (user_id, license_no, rating, admin_id) VALUES ('$safeUserId', '$safeLicense', 5.00, '$safeDriverAdminId')";
                    $subtypeResult = mysqli_query($conn, $sql);
                    if (!$subtypeResult || mysqli_affected_rows($conn) !== 1) $error = 'The driver details could not be saved.';
                }
            } else {
                $safeOfficeRoom = mysqli_real_escape_string($conn, $old['office_room_no']);
                $safeAdminLevel = mysqli_real_escape_string($conn, $old['admin_level']);
                $sql = "INSERT INTO ADMIN (user_id, office_room_no, admin_level) VALUES ('$safeUserId', '$safeOfficeRoom', '$safeAdminLevel')";
                $subtypeResult = mysqli_query($conn, $sql);
                if (!$subtypeResult || mysqli_affected_rows($conn) !== 1) $error = 'The admin details could not be saved.';
            }

            if ($error === '') {
                $safePhone = mysqli_real_escape_string($conn, $old['phone']);
                $sql = "INSERT INTO USER_PHONE (user_id, phone) VALUES ('$safeUserId', '$safePhone')";
                $phoneResult = mysqli_query($conn, $sql);
                if (!$phoneResult || mysqli_affected_rows($conn) !== 1) $error = 'The phone number could not be saved.';
            }
            if ($error === '') {
                if (mysqli_commit($conn)) {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $userId;
                    $_SESSION['username'] = $username;
                    $_SESSION['role'] = $role;
                    flash('success', 'Registration complete. Your username is ' . $username . '.');
                    $dashboard = $role === 'patient' ? 'patient.php' : ($role === 'driver' ? 'driver.php' : 'admin.php');
                    redirect($dashboard . '?user_id=' . rawurlencode($userId));
                } else {
                    $error = 'The registration transaction could not be completed.';
                    mysqli_rollback($conn);
                }
            } else {
                mysqli_rollback($conn);
            }
        }
    } elseif ($error === '') {
        $error = implode(' ', $errors);
    }
}

layout_header('', '');
?>
<section class="auth-shell"><div class="auth-card"><span class="kicker">JOIN THE RESPONSE NETWORK</span><h1>Register<br>with care.</h1><?php if ($error !== ''): ?><div class="alert error" role="alert"><?= e($error) ?></div><?php endif; ?><form method="post" novalidate><?= csrf_field() ?><div class="form-grid"><div class="field-full"><label for="role">Account type</label><select id="role" name="role"><option value="patient" <?= $old['role'] === 'patient' ? 'selected' : '' ?>>Patient</option><option value="driver" <?= $old['role'] === 'driver' ? 'selected' : '' ?>>Driver</option><option value="admin" <?= $old['role'] === 'admin' ? 'selected' : '' ?>>Admin</option></select></div><div class="field-full"><label for="username">Choose a unique username</label><input id="username" name="username" value="<?= e($old['username']) ?>" maxlength="30" pattern="[a-zA-Z0-9_.-]{3,30}" required><small class="mono">Use 3–30 letters, numbers, dot, underscore, or hyphen.</small></div><div><label for="first_name">First name</label><input id="first_name" name="first_name" value="<?= e($old['first_name']) ?>" maxlength="50" required></div><div><label for="last_name">Last name</label><input id="last_name" name="last_name" value="<?= e($old['last_name']) ?>" maxlength="50" required></div><div><label for="phone">Phone</label><input id="phone" name="phone" value="<?= e($old['phone']) ?>" maxlength="20" required></div><div><label for="password">Password</label><input id="password" name="password" type="password" minlength="8" maxlength="72" autocomplete="new-password" required></div><div id="patient-fields"><label for="emergency_type">Emergency type</label><input id="emergency_type" name="emergency_type" value="<?= e($old['emergency_type']) ?>" maxlength="50"></div><div id="age-field"><label for="age">Age</label><input id="age" name="age" value="<?= e($old['age']) ?>" type="number" min="0" max="120"></div><div id="driver-fields" class="field-full"><label for="license_no">Driver license number</label><input id="license_no" name="license_no" value="<?= e($old['license_no']) ?>" maxlength="20"></div><div id="admin-fields" class="field-full"><label for="admin_level">Admin level</label><select id="admin_level" name="admin_level"><option value="">Select admin level</option><?php foreach ($adminLevels as $level): ?><option value="<?= e($level) ?>" <?= $old['admin_level'] === $level ? 'selected' : '' ?>><?= e($level) ?></option><?php endforeach; ?></select><label for="office_room_no">Office room</label><select id="office_room_no" name="office_room_no"><option value="">Select office room</option><?php foreach ($officeRooms as $room): ?><option value="<?= e($room) ?>" <?= $old['office_room_no'] === $room ? 'selected' : '' ?>><?= e($room) ?></option><?php endforeach; ?></select></div></div><div class="form-actions"><button class="btn" type="submit">Create dispatch ID ↗</button><a href="index.php">Back to sign in</a></div></form></div></section>
<script>
const role = document.getElementById('role');
const patientFields = document.getElementById('patient-fields');
const ageField = document.getElementById('age-field');
const driverFields = document.getElementById('driver-fields');
const adminFields = document.getElementById('admin-fields');
function syncRole() { const patient = role.value === 'patient'; const driver = role.value === 'driver'; const admin = role.value === 'admin'; patientFields.hidden = !patient; ageField.hidden = !patient; driverFields.hidden = !driver; adminFields.hidden = !admin; }
role.addEventListener('change', syncRole);
syncRole();
</script>
<?php layout_footer(); ?>
