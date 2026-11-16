<?php
require_once __DIR__ . '/db.php';
$patientId = require_role('patient');
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $pickup = trim((string) ($_POST['pickup_location'] ?? ''));
    $drop = trim((string) ($_POST['drop_location'] ?? ''));
    $requestedType = (string) ($_POST['vehicle_type'] ?? '');
    $fare = $requestedType === 'Basic' ? 1500 : ($requestedType === 'ICU' ? 8000 : false);

    if ($pickup === '' || mb_strlen($pickup) > 100 || $drop === '' || mb_strlen($drop) > 100 || $fare === false) {
        $error = 'Please provide valid locations and a valid ambulance type.';
    } else {
        do {
            $rideId = 'RD-' . random_int(1000, 9999);
            $safeRideId = mysqli_real_escape_string($conn, $rideId);
            $sql = "SELECT EXISTS (SELECT 1 FROM RIDE_REQUEST WHERE ride_id = '$safeRideId') AS ride_exists";
            $result = mysqli_query($conn, $sql);
            if (!$result) die('Ride ID check failed: ' . mysqli_error($conn));
            $rideCheckRow = mysqli_fetch_array($result, MYSQLI_ASSOC);
        } while ((int) $rideCheckRow['ride_exists'] === 1);

        $safeRequestedType = mysqli_real_escape_string($conn, $requestedType);
        $sql = "SELECT a.admin_id FROM AMBULANCE a LEFT JOIN RIDE_REQUEST r ON r.admin_id = a.admin_id AND r.status IN ('Requested', 'Accepted') WHERE a.vehicle_type = '$safeRequestedType' AND a.available_status = 'Available' GROUP BY a.admin_id ORDER BY COUNT(r.ride_id), a.admin_id LIMIT 1";
        $result = mysqli_query($conn, $sql);
        if (!$result) die('Administrator routing query failed: ' . mysqli_error($conn));
        $dispatchAdminId = false;
        if (mysqli_num_rows($result) > 0) {
            $dispatchRow = mysqli_fetch_array($result, MYSQLI_ASSOC);
            $dispatchAdminId = $dispatchRow['admin_id'];
        }
        if (!$dispatchAdminId) {
            $sql = 'SELECT user_id FROM ADMIN ORDER BY user_id LIMIT 1';
            $result = mysqli_query($conn, $sql);
            if (!$result) die('Administrator fallback query failed: ' . mysqli_error($conn));
            if (mysqli_num_rows($result) > 0) {
                $fallbackRow = mysqli_fetch_array($result, MYSQLI_ASSOC);
                $dispatchAdminId = $fallbackRow['user_id'];
            }
        }
        if (!$dispatchAdminId) {
            $error = 'No administrator is available to receive this ride request.';
        } else {
            $safePickup = mysqli_real_escape_string($conn, $pickup);
            $safeDrop = mysqli_real_escape_string($conn, $drop);
            $safePatientId = mysqli_real_escape_string($conn, $patientId);
            $safeDispatchAdminId = mysqli_real_escape_string($conn, $dispatchAdminId);
            $sql = "INSERT INTO RIDE_REQUEST (ride_id, pickup_location, drop_location, status, fare, vehicle_type, request_date, patient_id, admin_id) VALUES ('$safeRideId', '$safePickup', '$safeDrop', 'Requested', $fare, '$safeRequestedType', CURDATE(), '$safePatientId', '$safeDispatchAdminId')";
            $result = mysqli_query($conn, $sql);
            if (!$result) {
                $error = 'The request could not be saved: ' . mysqli_error($conn);
            } elseif (mysqli_affected_rows($conn) === 1) {
                flash('success', 'Ride ' . $rideId . ' is now visible to matching drivers.');
                redirect('patient_feature_02.php?user_id=' . rawurlencode($patientId));
            } else {
                $error = 'The request could not be saved. Please try again.';
            }
        }
    }
}

layout_header('Request a ride', '', $patientId);
?>
<div class="section-heading"><div><p class="eyebrow">PATIENT / FEATURE 02</p><h2>Request a ride</h2></div><a class="btn small" href="patient.php?user_id=<?= e($patientId) ?>">Patient home</a></div>
<?php if ($error !== ''): ?><div class="alert error" role="alert"><?= e($error) ?></div><?php endif; ?>
<section class="panel pink">
    <form method="post" class="form-grid">
        <?= csrf_field() ?><input type="hidden" name="action" value="request_ride">
        <div><label for="pickup_location">Pickup location</label><input id="pickup_location" name="pickup_location" maxlength="100" placeholder="Dhanmondi 27" required></div>
        <div><label for="drop_location">Drop location</label><input id="drop_location" name="drop_location" maxlength="100" placeholder="Square Hospital" required></div>
        <div><label for="ride_type">Ambulance class</label><select id="ride_type" name="vehicle_type"><option value="Basic">Basic</option><option value="ICU">ICU</option></select></div>
        <div><label for="fare">Fixed fare estimate (৳)</label><input id="fare" name="fare" type="number" value="1500" readonly aria-describedby="fare-note"><small id="fare-note" class="mono">Basic ৳1,500 · ICU ৳8,000</small></div>
        <div class="field-full form-actions"><button class="btn" type="submit">Broadcast ride request ↗</button></div>
    </form>
</section>
<script>
const rideType = document.getElementById('ride_type');
const fareInput = document.getElementById('fare');
const fareByType = { Basic: 1500, ICU: 8000 };
function updateFareEstimate() { fareInput.value = fareByType[rideType.value] || 1500; }
rideType.addEventListener('change', updateFareEstimate);
updateFareEstimate();
</script>
<?php layout_footer(); ?>
