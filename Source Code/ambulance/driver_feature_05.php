<?php
require_once __DIR__ . '/db.php';
$driverId = require_role('driver');
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $rideId = clean_id((string) ($_POST['ride_id'] ?? ''));
    $regNo = strtoupper(trim((string) ($_POST['reg_no'] ?? '')));
    if (!mysqli_begin_transaction($conn)) {
        $error = 'The acceptance transaction could not start.';
    } else {
        $error = '';
        $safeRegNo = mysqli_real_escape_string($conn, $regNo);
        $safeRideId = mysqli_real_escape_string($conn, $rideId);
        $sql = "SELECT reg_no, vehicle_type, admin_cost, admin_id FROM AMBULANCE WHERE reg_no = '$safeRegNo' AND available_status = 'Available' FOR UPDATE";
        $result = mysqli_query($conn, $sql);
        if (!$result) die('Ambulance lookup failed: ' . mysqli_error($conn));
        $ambulance = mysqli_num_rows($result) > 0 ? mysqli_fetch_array($result, MYSQLI_ASSOC) : false;
        if (!$ambulance) {
            $error = 'That ambulance is no longer available.';
        } else {
            $sql = "SELECT r.ride_id, r.fare, r.vehicle_type, r.admin_id FROM RIDE_REQUEST r INNER JOIN PATIENT p ON r.patient_id = p.user_id WHERE r.ride_id = '$safeRideId' AND r.status = 'Requested' FOR UPDATE";
            $result = mysqli_query($conn, $sql);
            if (!$result) die('Ride lookup failed: ' . mysqli_error($conn));
            $ride = mysqli_num_rows($result) > 0 ? mysqli_fetch_array($result, MYSQLI_ASSOC) : false;
            if (!$ride) {
                $error = 'This ride is no longer available for acceptance.';
            } else {
                $expectedFare = $ride['vehicle_type'] === 'Basic' ? 1500 : ($ride['vehicle_type'] === 'ICU' ? 8000 : false);
                $adminCost = (int) $ambulance['admin_cost'];
                $driverEarning = $expectedFare === false ? -1 : $expectedFare - $adminCost;
                if ($expectedFare === false || (int) $ride['fare'] !== $expectedFare || $ambulance['vehicle_type'] !== $ride['vehicle_type'] || $ambulance['admin_id'] !== $ride['admin_id'] || $adminCost <= 0 || $driverEarning < 0) {
                    $error = 'The selected ambulance must match the requested ride type and fixed fare.';
                } else {
                    $safeDriverId = mysqli_real_escape_string($conn, $driverId);
                    $sql = "INSERT INTO ACCEPTS (ride_id, driver_id, reg_no, admin_cost, driver_earning, settlement_status) VALUES ('$safeRideId', '$safeDriverId', '$safeRegNo', $adminCost, $driverEarning, 'Pending')";
                    $acceptResult = mysqli_query($conn, $sql);
                    $acceptOk = $acceptResult && mysqli_affected_rows($conn) === 1;
                    $sql = "UPDATE RIDE_REQUEST SET status = 'Accepted' WHERE ride_id = '$safeRideId' AND status = 'Requested'";
                    $statusResult = mysqli_query($conn, $sql);
                    $statusOk = $statusResult && mysqli_affected_rows($conn) === 1;
                    $sql = "UPDATE AMBULANCE SET available_status = 'Busy' WHERE reg_no = '$safeRegNo' AND available_status = 'Available'";
                    $busyResult = mysqli_query($conn, $sql);
                    $busyOk = $busyResult && mysqli_affected_rows($conn) === 1;
                    if (!$acceptOk || !$statusOk || !$busyOk) $error = 'The ride acceptance could not be completed.';
                }
            }
        }
        if ($error === '') {
            if (mysqli_commit($conn)) {
                flash('success', 'Ride ' . $rideId . ' accepted. Admin cost: ৳' . number_format($adminCost) . '; driver earning: ৳' . number_format($driverEarning) . '.');
                redirect('driver_feature_05.php?user_id=' . rawurlencode($driverId));
            } else {
                $error = 'The ride acceptance transaction could not be completed.';
                mysqli_rollback($conn);
            }
        } else {
            mysqli_rollback($conn);
        }
    }
}

$sql = "SELECT r.ride_id, r.pickup_location, r.drop_location, r.fare, r.vehicle_type, r.admin_id, r.request_date, u.username AS patient_username, u.first_name AS patient_first_name, u.last_name AS patient_last_name, ph.phone AS patient_phone FROM RIDE_REQUEST r INNER JOIN PATIENT p ON r.patient_id = p.user_id INNER JOIN `USER` u ON p.user_id = u.user_id LEFT JOIN USER_PHONE ph ON ph.user_id = p.user_id WHERE r.status = 'Requested' ORDER BY r.request_date DESC, r.ride_id DESC";
$result = mysqli_query($conn, $sql);
if (!$result) die('Pending rides query failed: ' . mysqli_error($conn));
$pending = [];
if (mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
        $pending[] = $row;
    }
}
$sql = "SELECT reg_no, vehicle_type, hourly_rate, admin_cost, available_status, admin_id FROM AMBULANCE WHERE available_status = 'Available' ORDER BY vehicle_type, reg_no";
$result = mysqli_query($conn, $sql);
if (!$result) die('Available ambulances query failed: ' . mysqli_error($conn));
$vehicles = [];
if (mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
        $vehicles[] = $row;
    }
}

layout_header('Accept a ride', '', $driverId);
?>
<div class="section-heading"><div><p class="eyebrow">DRIVER / FEATURE 05</p><h2>Pending ride requests</h2></div><a class="btn small" href="driver.php?user_id=<?= e($driverId) ?>">Driver home</a></div>
<?php if ($error !== ''): ?><div class="alert error" role="alert"><?= e($error) ?></div><?php endif; ?>
<section class="table-wrap"><?php if (!$pending): ?><div class="empty">No pending requests right now. The grid is quiet.</div><?php else: ?><table><thead><tr><th>Ride</th><th>Route</th><th>Patient contact</th><th>Fare</th><th>Requested</th><th>Accept with ambulance</th></tr></thead><tbody><?php foreach ($pending as $ride): ?><tr><td class="mono"><?= e($ride['ride_id']) ?></td><td><?= e($ride['pickup_location']) ?><br>↓ <?= e($ride['drop_location']) ?></td><td><strong><?= e($ride['patient_first_name'] . ' ' . $ride['patient_last_name']) ?></strong><br><span class="mono">@<?= e($ride['patient_username']) ?></span><br><?= phone_contact($ride['patient_phone'] ?? null) ?></td><td>৳<?= number_format((int) $ride['fare']) ?></td><td><?= e($ride['request_date']) ?></td><td><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="ride_id" value="<?= e($ride['ride_id']) ?>"><select name="reg_no" required><option value="">Select <?= e($ride['vehicle_type']) ?> vehicle</option><?php foreach ($vehicles as $vehicle): ?><option value="<?= e($vehicle['reg_no']) ?>" data-vehicle-type="<?= e($vehicle['vehicle_type']) ?>" <?= ($vehicle['vehicle_type'] !== $ride['vehicle_type'] || $vehicle['admin_id'] !== $ride['admin_id']) ? 'disabled' : '' ?>><?= e($vehicle['reg_no'] . ' · ' . $vehicle['vehicle_type'] . ' · fare ৳' . $vehicle['hourly_rate'] . ' · admin ৳' . $vehicle['admin_cost']) ?></option><?php endforeach; ?></select><button class="btn small teal" type="submit">Accept</button></form></td></tr><?php endforeach; ?></tbody></table><?php endif; ?></section>
<?php layout_footer(); ?>
