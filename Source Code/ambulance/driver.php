<?php
require_once __DIR__ . '/db.php';
$driverId = require_role('driver');

$safeDriverId = mysqli_real_escape_string($conn, $driverId);
$sql = "SELECT u.username, u.first_name, u.last_name, d.license_no, d.rating, ph.phone FROM `USER` u INNER JOIN DRIVER d ON u.user_id = d.user_id LEFT JOIN USER_PHONE ph ON ph.user_id = u.user_id WHERE u.user_id = '$safeDriverId'";
$result = mysqli_query($conn, $sql);
if (!$result) die('Driver profile query failed: ' . mysqli_error($conn));
if (mysqli_num_rows($result) > 0) {
    $profile = mysqli_fetch_array($result, MYSQLI_ASSOC);
} else {
    die('Driver profile could not be found.');
}


layout_header('Driver home', '', $driverId);
?>
<section class="hero">
    <div><span class="kicker">DRIVER RESPONSE DESK</span><h1>Take the<br>next call.</h1><p class="lede">Choose the driver feature you need.</p></div>
    <div class="panel yellow"><p class="eyebrow">DRIVER PROFILE</p><h3><?= e($profile['first_name'] . ' ' . $profile['last_name']) ?></h3><p class="mono">@<?= e($profile['username']) ?> · response network / license <?= e($profile['license_no']) ?></p><p><?= phone_contact($profile['phone'] ?? null) ?></p><p class="rating"><?= number_format((float) $profile['rating'], 2) ?> / 5.00</p></div>
</section>
<section class="grid grid-3">
    <a class="card" href="driver_feature_04.php?user_id=<?= e($driverId) ?>"><p class="eyebrow">FEATURE 04</p><h3>Earnings & reviews</h3><p>View total driver earnings and completed trips with patient reviews.</p></a>
    <a class="card pink" href="driver_feature_05.php?user_id=<?= e($driverId) ?>"><p class="eyebrow">FEATURE 05</p><h3>Pending ride requests</h3><p>Review pending requests and select an available ambulance.</p></a>
    <a class="card teal" href="driver_feature_06.php?user_id=<?= e($driverId) ?>"><p class="eyebrow">FEATURE 06</p><h3>Complete a trip</h3><p>Complete active trips and settle the admin and driver split.</p></a>
</section>
<?php layout_footer(); ?>
