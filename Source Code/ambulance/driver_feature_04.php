<?php
require_once __DIR__ . '/db.php';
$driverId = require_role('driver');

$safeDriverId = mysqli_real_escape_string($conn, $driverId);
$sql = "SELECT r.ride_id, r.pickup_location, r.drop_location, r.fare, r.vehicle_type, r.request_date, r.passenger_rating, r.feedback, u.username AS patient_username, u.first_name AS patient_first_name, u.last_name AS patient_last_name, ph.phone AS patient_phone, ac.reg_no, ac.admin_cost, ac.driver_earning, ac.settlement_status FROM RIDE_REQUEST r INNER JOIN ACCEPTS ac ON r.ride_id = ac.ride_id INNER JOIN PATIENT p ON r.patient_id = p.user_id INNER JOIN `USER` u ON p.user_id = u.user_id LEFT JOIN USER_PHONE ph ON ph.user_id = p.user_id WHERE ac.driver_id = '$safeDriverId' AND r.status = 'Completed' ORDER BY r.request_date DESC, r.ride_id DESC";
$result = mysqli_query($conn, $sql);
if (!$result) die('Completed trips query failed: ' . mysqli_error($conn));
$completedTrips = [];
if (mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
        $completedTrips[] = $row;
    }
}

$sql = "SELECT COALESCE(SUM(ac.driver_earning), 0) AS total_earnings FROM ACCEPTS ac INNER JOIN RIDE_REQUEST r ON r.ride_id = ac.ride_id WHERE ac.driver_id = '$safeDriverId' AND r.status = 'Completed' AND ac.settlement_status = 'Settled'";
$result = mysqli_query($conn, $sql);
if (!$result) die('Driver earnings query failed: ' . mysqli_error($conn));
$earningsRow = mysqli_fetch_array($result, MYSQLI_ASSOC);
$totalEarnings = (int) ($earningsRow['total_earnings'] ?? 0);

layout_header('Driver earnings', '', $driverId);
?>
<div class="section-heading"><div><p class="eyebrow">DRIVER / FEATURE 04</p><h2>Earnings & completed trips</h2></div><a class="btn small" href="driver.php?user_id=<?= e($driverId) ?>">Driver home</a></div>
<div class="stats"><div class="stat"><span class="stat-label">Total driver earning</span><span class="stat-value">৳<?= number_format($totalEarnings) ?></span></div><div class="stat"><span class="stat-label">Completed trips</span><span class="stat-value"><?= count($completedTrips) ?></span></div></div>
<section class="table-wrap"><?php if (!$completedTrips): ?><div class="empty">Completed trips and driver earnings will appear here after your first completed ride.</div><?php else: ?><table><thead><tr><th>Ride</th><th>Route</th><th>Patient contact</th><th>Fare</th><th>Admin cost</th><th>Your earning</th><th>Patient review</th><th>Settlement</th></tr></thead><tbody><?php foreach ($completedTrips as $ride): ?><tr><td class="mono"><?= e($ride['ride_id']) ?><br><small><?= e($ride['request_date']) ?></small></td><td><?= e($ride['pickup_location']) ?><br>↓ <?= e($ride['drop_location']) ?></td><td><strong><?= e($ride['patient_first_name'] . ' ' . $ride['patient_last_name']) ?></strong><br><span class="mono">@<?= e($ride['patient_username']) ?></span><br><?= phone_contact($ride['patient_phone'] ?? null) ?></td><td>৳<?= number_format((int) $ride['fare']) ?></td><td>৳<?= number_format((int) $ride['admin_cost']) ?></td><td><strong>৳<?= number_format((int) $ride['driver_earning']) ?></strong></td><td><?php if ($ride['passenger_rating'] !== null): ?><span class="rating"><?= str_repeat('★', (int) $ride['passenger_rating']) ?></span><br><small><?= e($ride['feedback'] ?? '') ?></small><?php else: ?><span class="mono">No review yet</span><?php endif; ?></td><td><span class="badge completed"><?= e($ride['settlement_status']) ?></span></td></tr><?php endforeach; ?></tbody></table><?php endif; ?></section>
<?php layout_footer(); ?>
