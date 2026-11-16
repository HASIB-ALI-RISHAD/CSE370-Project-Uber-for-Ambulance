<?php
require_once __DIR__ . '/db.php';
$patientId = require_role('patient');
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $rideId = clean_id((string) ($_POST['ride_id'] ?? ''));
    $rating = filter_var($_POST['rating'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5]]);
    $feedback = trim((string) ($_POST['feedback'] ?? ''));
    if (!preg_match('/^RD-[0-9]{4}$/', $rideId) || $rating === false || mb_strlen($feedback) > 500) {
        $error = 'Please provide a rating from 1 to 5 and feedback under 500 characters.';
    } else {
        if (!mysqli_begin_transaction($conn)) {
            $error = 'The review transaction could not start.';
        } else {
            $safeRideId = mysqli_real_escape_string($conn, $rideId);
            $safePatientId = mysqli_real_escape_string($conn, $patientId);
            $safeFeedback = mysqli_real_escape_string($conn, $feedback);
            $feedbackSql = $feedback === '' ? 'NULL' : "'$safeFeedback'";
            $sql = "UPDATE RIDE_REQUEST SET passenger_rating = $rating, feedback = $feedbackSql WHERE ride_id = '$safeRideId' AND patient_id = '$safePatientId' AND status = 'Completed' AND passenger_rating IS NULL";
            $result = mysqli_query($conn, $sql);
            if (!$result) {
                mysqli_rollback($conn);
                $error = 'The review could not be saved: ' . mysqli_error($conn);
            } elseif (mysqli_affected_rows($conn) !== 1) {
                mysqli_rollback($conn);
                flash('success', 'Only completed rides can be reviewed.');
                redirect('patient_feature_03.php?user_id=' . rawurlencode($patientId));
            } else {
                $sql = "SELECT driver_id FROM ACCEPTS WHERE ride_id = '$safeRideId'";
                $result = mysqli_query($conn, $sql);
                if (!$result) die('Driver lookup failed: ' . mysqli_error($conn));
                if (mysqli_num_rows($result) === 0) {
                    mysqli_rollback($conn);
                    $error = 'The driver rating could not be refreshed.';
                } else {
                    $driverRow = mysqli_fetch_array($result, MYSQLI_ASSOC);
                    $safeDriverId = mysqli_real_escape_string($conn, $driverRow['driver_id']);
                    $sql = "SELECT COALESCE(AVG(r.passenger_rating), 5.00) AS average_rating FROM RIDE_REQUEST r INNER JOIN ACCEPTS ac ON r.ride_id = ac.ride_id WHERE ac.driver_id = '$safeDriverId' AND r.status = 'Completed' AND r.passenger_rating IS NOT NULL";
                    $result = mysqli_query($conn, $sql);
                    if (!$result) die('Driver rating average query failed: ' . mysqli_error($conn));
                    $averageRow = mysqli_fetch_array($result, MYSQLI_ASSOC);
                    $averageRating = (float) $averageRow['average_rating'];
                    $sql = "UPDATE DRIVER SET rating = $averageRating WHERE user_id = '$safeDriverId'";
                    $result = mysqli_query($conn, $sql);
                    if (!$result) die('Driver rating update failed: ' . mysqli_error($conn));
                    if (mysqli_commit($conn)) {
                        flash('success', 'Your review has been recorded and the driver average rating was updated.');
                        redirect('patient_feature_03.php?user_id=' . rawurlencode($patientId));
                    } else {
                        mysqli_rollback($conn);
                        $error = 'The driver rating could not be refreshed.';
                    }
                }
            }
        }
    }
}

$safePatientId = mysqli_real_escape_string($conn, $patientId);
$sql = "SELECT r.ride_id, r.pickup_location, r.drop_location, r.fare, r.vehicle_type, r.request_date, r.status, r.passenger_rating, r.feedback, u.username AS driver_username, u.first_name AS driver_first_name, UPPER(u.last_name) AS driver_surname, ph.phone AS driver_phone FROM RIDE_REQUEST r LEFT JOIN ACCEPTS ac ON r.ride_id = ac.ride_id LEFT JOIN DRIVER d ON ac.driver_id = d.user_id LEFT JOIN `USER` u ON d.user_id = u.user_id LEFT JOIN USER_PHONE ph ON ph.user_id = d.user_id WHERE r.patient_id = '$safePatientId' ORDER BY r.request_date DESC, r.ride_id DESC";
$result = mysqli_query($conn, $sql);
if (!$result) die('Ride history query failed: ' . mysqli_error($conn));
$history = [];
if (mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
        $history[] = $row;
    }
}

layout_header('Ride history', '', $patientId);
?>
<div class="section-heading"><div><p class="eyebrow">PATIENT / FEATURE 03</p><h2>Ride history</h2></div><a class="btn small" href="patient.php?user_id=<?= e($patientId) ?>">Patient home</a></div>
<?php if ($error !== ''): ?><div class="alert error" role="alert"><?= e($error) ?></div><?php endif; ?>
<section class="table-wrap">
    <?php if (!$history): ?><div class="empty">Your ride log is empty.</div><?php else: ?><table><thead><tr><th>Ride</th><th>Route</th><th>Fare</th><th>Status</th><th>Driver contact</th><th>Review</th></tr></thead><tbody>
    <?php foreach ($history as $ride): ?><tr><td class="mono"><?= e($ride['ride_id']) ?><br><small><?= e($ride['request_date']) ?></small></td><td><?= e($ride['pickup_location']) ?><br>↓ <?= e($ride['drop_location']) ?><br><span class="mono"><?= e($ride['vehicle_type']) ?></span></td><td>৳<?= number_format((int) $ride['fare']) ?></td><td><span class="badge <?= strtolower(e($ride['status'])) ?>"><?= e($ride['status']) ?></span></td><td><?php if ($ride['driver_surname']): ?><span class="mono">@<?= e($ride['driver_username']) ?> / <?= e($ride['driver_first_name'] . ' ' . $ride['driver_surname']) ?></span><br><?= phone_contact($ride['driver_phone'] ?? null) ?><?php else: ?>Awaiting driver<?php endif; ?></td><td>
    <?php if ($ride['status'] === 'Completed' && $ride['passenger_rating'] === null): ?><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="ride_id" value="<?= e($ride['ride_id']) ?>"><select name="rating" aria-label="Rating"><option value="5">5 / excellent</option><option value="4">4 / good</option><option value="3">3 / fair</option><option value="2">2 / poor</option><option value="1">1 / bad</option></select><input name="feedback" placeholder="Short note" maxlength="500"><button class="btn small yellow" type="submit">Save</button></form>
    <?php elseif ($ride['passenger_rating'] !== null): ?><span class="rating"><?= str_repeat('★', (int) $ride['passenger_rating']) ?></span><br><small><?= e($ride['feedback'] ?? '') ?></small><?php else: ?><span class="mono">not yet eligible</span><?php endif; ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?>
</section>
<?php layout_footer(); ?>
