<?php
require_once __DIR__ . '/db.php';
$driverId = require_role('driver');
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $rideId = clean_id((string) ($_POST['ride_id'] ?? ''));
    if (!mysqli_begin_transaction($conn)) {
        $error = 'The completion transaction could not start.';
    } else {
        $error = '';
        $safeRideId = mysqli_real_escape_string($conn, $rideId);
        $safeDriverId = mysqli_real_escape_string($conn, $driverId);
        $sql = "SELECT reg_no, admin_cost, driver_earning, settlement_status FROM ACCEPTS WHERE ride_id = '$safeRideId' AND driver_id = '$safeDriverId'";
        $result = mysqli_query($conn, $sql);
        if (!$result) die('Accepted ride lookup failed: ' . mysqli_error($conn));
        $assignment = mysqli_num_rows($result) > 0 ? mysqli_fetch_array($result, MYSQLI_ASSOC) : false;
        if (!$assignment) {
            $error = 'Only your accepted rides can be completed.';
        } else {
            $sql = "SELECT status, fare FROM RIDE_REQUEST WHERE ride_id = '$safeRideId'";
            $result = mysqli_query($conn, $sql);
            if (!$result) die('Ride status lookup failed: ' . mysqli_error($conn));
            $rideToComplete = mysqli_num_rows($result) > 0 ? mysqli_fetch_array($result, MYSQLI_ASSOC) : false;
            if (!$rideToComplete || $rideToComplete['status'] !== 'Accepted') {
                $error = 'This ride is already completed or unavailable.';
            } elseif ($assignment['settlement_status'] !== 'Pending' || (int) $assignment['admin_cost'] + (int) $assignment['driver_earning'] !== (int) $rideToComplete['fare']) {
                $error = 'The payment split is invalid. Ask the administrator to review the ambulance cost.';
            } else {
                $sql = "UPDATE RIDE_REQUEST SET status = 'Completed' WHERE ride_id = '$safeRideId' AND status = 'Accepted'";
                $completeResult = mysqli_query($conn, $sql);
                if (!$completeResult || mysqli_affected_rows($conn) !== 1) {
                    $error = 'The ride status could not be updated.';
                } else {
                    $sql = "SELECT COALESCE(AVG(r.passenger_rating), 5.00) AS average_rating FROM RIDE_REQUEST r INNER JOIN ACCEPTS ac ON r.ride_id = ac.ride_id WHERE ac.driver_id = '$safeDriverId' AND r.passenger_rating IS NOT NULL";
                    $result = mysqli_query($conn, $sql);
                    if (!$result) die('Driver rating query failed: ' . mysqli_error($conn));
                    $ratingRow = mysqli_fetch_array($result, MYSQLI_ASSOC);
                    $averageRating = (float) $ratingRow['average_rating'];
                    $sql = "UPDATE DRIVER SET rating = $averageRating WHERE user_id = '$safeDriverId'";
                    $ratingUpdateResult = mysqli_query($conn, $sql);
                    $sql = "UPDATE ACCEPTS SET settlement_status = 'Settled', settled_at = CURDATE() WHERE ride_id = '$safeRideId' AND driver_id = '$safeDriverId' AND settlement_status = 'Pending'";
                    $settleResult = mysqli_query($conn, $sql);
                    $settleOk = $settleResult && mysqli_affected_rows($conn) === 1;
                    $safeRegNo = mysqli_real_escape_string($conn, $assignment['reg_no']);
                    $sql = "UPDATE AMBULANCE SET available_status = 'Available' WHERE reg_no = '$safeRegNo'";
                    $releaseResult = mysqli_query($conn, $sql);
                    $releaseOk = $releaseResult && mysqli_affected_rows($conn) === 1;
                    if (!$ratingUpdateResult || !$settleOk || !$releaseOk) $error = 'The settlement or ambulance release could not be completed.';
                }
            }
        }
        if ($error === '') {
            if (mysqli_commit($conn)) {
                flash('success', 'Ride ' . $rideId . ' completed. Admin cost paid: ৳' . number_format((int) $assignment['admin_cost']) . '; driver earned: ৳' . number_format((int) $assignment['driver_earning']) . '.');
                redirect('driver_feature_06.php?user_id=' . rawurlencode($driverId));
            } else {
                $error = 'The completion transaction could not be completed.';
                mysqli_rollback($conn);
            }
        } else {
            mysqli_rollback($conn);
        }
    }
}

$safeDriverId = mysqli_real_escape_string($conn, $driverId);
$sql = "SELECT r.ride_id, r.pickup_location, r.drop_location, r.fare, r.vehicle_type, r.request_date, u.username AS patient_username, u.first_name AS patient_first_name, u.last_name AS patient_last_name, ph.phone AS patient_phone, ac.reg_no, ac.admin_cost, ac.driver_earning FROM RIDE_REQUEST r INNER JOIN ACCEPTS ac ON r.ride_id = ac.ride_id INNER JOIN PATIENT p ON r.patient_id = p.user_id INNER JOIN `USER` u ON p.user_id = u.user_id LEFT JOIN USER_PHONE ph ON ph.user_id = p.user_id WHERE ac.driver_id = '$safeDriverId' AND r.status = 'Accepted' ORDER BY r.request_date DESC";
$result = mysqli_query($conn, $sql);
if (!$result) die('Active trips query failed: ' . mysqli_error($conn));
$active = [];
if (mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
        $active[] = $row;
    }
}

layout_header('Complete a trip', '', $driverId);
?>
<div class="section-heading"><div><p class="eyebrow">DRIVER / FEATURE 06</p><h2>Active trips</h2></div><a class="btn small" href="driver.php?user_id=<?= e($driverId) ?>">Driver home</a></div>
<?php if ($error !== ''): ?><div class="alert error" role="alert"><?= e($error) ?></div><?php endif; ?>
<section class="grid grid-2"><?php if (!$active): ?><div class="empty">No active assignments. Your next acceptance will appear here.</div><?php else: ?><?php foreach ($active as $ride): ?><div class="card"><p class="eyebrow"><?= e($ride['ride_id']) ?> / <?= e($ride['reg_no']) ?></p><h3><?= e($ride['pickup_location']) ?> → <?= e($ride['drop_location']) ?></h3><p><strong><?= e($ride['patient_first_name'] . ' ' . $ride['patient_last_name']) ?></strong><br><span class="mono">@<?= e($ride['patient_username']) ?></span><br><?= phone_contact($ride['patient_phone'] ?? null) ?></p><p class="mono">Fare ৳<?= number_format((int) $ride['fare']) ?> · admin ৳<?= number_format((int) $ride['admin_cost']) ?> · you earn ৳<?= number_format((int) $ride['driver_earning']) ?></p><form method="post" class="form-actions"><?= csrf_field() ?><input type="hidden" name="ride_id" value="<?= e($ride['ride_id']) ?>"><button class="btn yellow" type="submit">Mark completed ↗</button></form></div><?php endforeach; ?><?php endif; ?></section>
<?php layout_footer(); ?>
