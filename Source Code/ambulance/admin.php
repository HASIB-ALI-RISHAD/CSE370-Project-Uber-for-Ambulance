<?php
require_once __DIR__ . '/db.php';
$adminId = require_role('admin');

$safeAdminId = mysqli_real_escape_string($conn, $adminId);
$sql = "SELECT u.username, u.first_name, u.last_name, a.admin_level, a.office_room_no, ph.phone FROM `USER` u INNER JOIN ADMIN a ON a.user_id = u.user_id LEFT JOIN USER_PHONE ph ON ph.user_id = u.user_id WHERE u.user_id = '$safeAdminId'";
$result = mysqli_query($conn, $sql);
if (!$result) die('Admin profile query failed: ' . mysqli_error($conn));
if (mysqli_num_rows($result) > 0) {
    $profile = mysqli_fetch_array($result, MYSQLI_ASSOC);
} else {
    die('Admin profile could not be found.');
}

layout_header('Admin home', '', $adminId);
?>
<section class="hero">
    <div><span class="kicker">ADMIN FLEET INTELLIGENCE</span><h1>See the<br>whole grid.</h1><p class="lede">Choose the administration feature you need.</p></div>
    <div class="panel yellow"><p class="eyebrow">ADMIN PROFILE</p><h3><?= e($profile['first_name'] . ' ' . $profile['last_name']) ?></h3><p class="mono">@<?= e($profile['username']) ?> · <?= e($profile['admin_level']) ?> / room <?= e($profile['office_room_no']) ?></p><p><?= phone_contact($profile['phone'] ?? null) ?></p></div>
</section>
<section class="grid grid-3">
    <a class="card" href="admin_feature_07.php?user_id=<?= e($adminId) ?>"><p class="eyebrow">FEATURE 07</p><h3>Fleet management</h3><p>Deploy ambulances and see your earning.</p></a>
    <a class="card pink" href="admin_feature_08.php?user_id=<?= e($adminId) ?>"><p class="eyebrow">FEATURE 08</p><h3>Driver benchmark</h3><p>Review top driver earners and drivers below the ride benchmark.</p></a>
    <a class="card teal" href="admin_feature_09.php?user_id=<?= e($adminId) ?>"><p class="eyebrow">FEATURE 09</p><h3>Call the network</h3><p>Find patient and driver contact details in one directory.</p></a>
</section>
<?php layout_footer(); ?>
