<?php
require_once __DIR__ . '/db.php';
$adminId = require_role('admin');

$safeAdminId = mysqli_real_escape_string($conn, $adminId);
$sql = "SELECT d.user_id AS driver_id, u.username, u.first_name, u.last_name, SUM(ac.driver_earning) AS total_earnings FROM DRIVER d INNER JOIN `USER` u ON u.user_id = d.user_id INNER JOIN ACCEPTS ac ON d.user_id = ac.driver_id INNER JOIN RIDE_REQUEST r ON ac.ride_id = r.ride_id WHERE d.admin_id = '$safeAdminId' AND r.admin_id = '$safeAdminId' AND r.status = 'Completed' AND ac.settlement_status = 'Settled' GROUP BY d.user_id, u.username, u.first_name, u.last_name ORDER BY total_earnings DESC LIMIT 3";
$result = mysqli_query($conn, $sql);
if (!$result) die('Top driver query failed: ' . mysqli_error($conn));
$topDrivers = [];
if (mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
        $topDrivers[] = $row;
    }
}
$sql = "SELECT d.user_id AS driver_id, u.username, u.first_name, u.last_name, ph.phone, COUNT(CASE WHEN r.status = 'Completed' THEN ac.ride_id END) AS completed_rides FROM DRIVER d INNER JOIN `USER` u ON u.user_id = d.user_id LEFT JOIN USER_PHONE ph ON ph.user_id = d.user_id LEFT JOIN ACCEPTS ac ON d.user_id = ac.driver_id LEFT JOIN RIDE_REQUEST r ON ac.ride_id = r.ride_id AND r.admin_id = '$safeAdminId' WHERE d.admin_id = '$safeAdminId' GROUP BY d.user_id, u.username, u.first_name, u.last_name, ph.phone HAVING COUNT(CASE WHEN r.status = 'Completed' THEN ac.ride_id END) < COALESCE((SELECT AVG(ride_count) FROM (SELECT COUNT(ac2.ride_id) AS ride_count FROM ACCEPTS ac2 INNER JOIN RIDE_REQUEST r2 ON ac2.ride_id = r2.ride_id WHERE r2.admin_id = '$safeAdminId' AND r2.status = 'Completed' GROUP BY ac2.driver_id) AS average_rides), 1)";
$result = mysqli_query($conn, $sql);
if (!$result) die('Driver benchmark query failed: ' . mysqli_error($conn));
$inactiveDrivers = [];
if (mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
        $inactiveDrivers[] = $row;
    }
}

layout_header('Driver earnings report', '', $adminId);
?>
<div class="section-heading"><div><p class="eyebrow">ADMIN / FEATURE 08</p><h2>Driver earnings & benchmark</h2></div><a class="btn small" href="admin.php?user_id=<?= e($adminId) ?>">Admin home</a></div>
<section class="section-heading"><div><p class="eyebrow">TOP THREE</p><h2>Top-3 driver earners</h2></div></section>
<section class="grid grid-3"><?php if (!$topDrivers): ?><div class="empty">No completed driver earnings yet.</div><?php else: ?><?php foreach ($topDrivers as $index => $driver): ?><div class="card <?= $index === 0 ? 'yellow' : '' ?>"><p class="eyebrow">RANK 0<?= $index + 1 ?></p><h3><?= e($driver['first_name'] . ' ' . $driver['last_name']) ?></h3><p class="mono">@<?= e($driver['username']) ?> / <?= e($driver['driver_id']) ?></p><strong>৳<?= number_format((int) $driver['total_earnings']) ?></strong></div><?php endforeach; ?><?php endif; ?></section>
<section class="section-heading"><div><p class="eyebrow">BENCHMARK</p><h2>Drivers below benchmark</h2></div></section>
<section class="table-wrap"><table><thead><tr><th>Driver</th><th>Completed rides</th><th>Driver phone</th></tr></thead><tbody><?php if (!$inactiveDrivers): ?><tr><td colspan="3">No drivers currently fall below the completed-ride benchmark.</td></tr><?php else: ?><?php foreach ($inactiveDrivers as $driver): ?><tr><td><strong><?= e($driver['first_name'] . ' ' . $driver['last_name']) ?></strong><br><span class="mono">@<?= e($driver['username']) ?> / <?= e($driver['driver_id']) ?></span></td><td><?= (int) $driver['completed_rides'] ?></td><td><?= phone_contact($driver['phone'] ?? null) ?></td></tr><?php endforeach; ?><?php endif; ?></tbody></table></section>
<?php layout_footer(); ?>
