<?php
require_once __DIR__ . '/db.php';
$patientId = require_role('patient');

$safePatientId = mysqli_real_escape_string($conn, $patientId);
$sql = "SELECT u.username, u.first_name, u.last_name, p.emergency_type, p.age, ph.phone FROM `USER` u INNER JOIN PATIENT p ON p.user_id = u.user_id LEFT JOIN USER_PHONE ph ON ph.user_id = u.user_id WHERE u.user_id = '$safePatientId'";
$result = mysqli_query($conn, $sql);
if (!$result) die('Patient profile query failed: ' . mysqli_error($conn));
if (mysqli_num_rows($result) > 0) {
    $profile = mysqli_fetch_array($result, MYSQLI_ASSOC);
} else {
    die('Patient profile could not be found.');
}

layout_header('Patient home', '', $patientId);
?>
<section class="hero">
    <div><span class="kicker">PATIENT CONTROL ROOM</span><h1>Care, on<br>your terms.</h1><p class="lede">Choose the patient feature you need.</p></div>
    <div class="panel yellow"><p class="eyebrow">PATIENT PROFILE</p><h3><?= e($profile['first_name'] . ' ' . $profile['last_name']) ?></h3><p class="mono">@<?= e($profile['username']) ?> · <?= e($profile['emergency_type']) ?> / age <?= e((string) $profile['age']) ?></p><p><?= phone_contact($profile['phone'] ?? null) ?></p></div>
</section>
<section class="grid grid-3">
    <a class="card" href="patient_feature_01.php?user_id=<?= e($patientId) ?>"><p class="eyebrow">FEATURE 01</p><h3>Find an ambulance</h3><p>Scan the administrator-managed fleet for available Basic or ICU vehicles.</p></a>
    <a class="card pink" href="patient_feature_02.php?user_id=<?= e($patientId) ?>"><p class="eyebrow">FEATURE 02</p><h3>Request a ride</h3><p>Submit a ride request using the fixed Basic or ICU fare.</p></a>
    <a class="card teal" href="patient_feature_03.php?user_id=<?= e($patientId) ?>"><p class="eyebrow">FEATURE 03</p><h3>Ride history</h3><p>Review completed rides, driver contact, and patient ratings.</p></a>
</section>
<?php layout_footer(); ?>
