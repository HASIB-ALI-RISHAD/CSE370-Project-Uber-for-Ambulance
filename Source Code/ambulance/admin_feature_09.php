<?php
require_once __DIR__ . '/db.php';
$adminId = require_role('admin');

$safeAdminId = mysqli_real_escape_string($conn, $adminId);
$sql = "SELECT p.user_id, u.username, u.first_name, u.last_name, p.emergency_type, p.age, ph.phone FROM PATIENT p INNER JOIN `USER` u ON u.user_id = p.user_id LEFT JOIN USER_PHONE ph ON ph.user_id = p.user_id WHERE EXISTS (SELECT 1 FROM RIDE_REQUEST r WHERE r.patient_id = p.user_id AND r.admin_id = '$safeAdminId') ORDER BY u.last_name, u.first_name";
$result = mysqli_query($conn, $sql);
if (!$result) die('Patient contacts query failed: ' . mysqli_error($conn));
$patientContacts = [];
if (mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
        $patientContacts[] = $row;
    }
}
$sql = "SELECT d.user_id, u.username, u.first_name, u.last_name, d.license_no, d.rating, ph.phone FROM DRIVER d INNER JOIN `USER` u ON u.user_id = d.user_id LEFT JOIN USER_PHONE ph ON ph.user_id = d.user_id WHERE d.admin_id = '$safeAdminId' ORDER BY u.last_name, u.first_name";
$result = mysqli_query($conn, $sql);
if (!$result) die('Driver contacts query failed: ' . mysqli_error($conn));
$driverContacts = [];
if (mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
        $driverContacts[] = $row;
    }
}

layout_header('Call the network', '', $adminId);
?>
<div class="section-heading"><div><p class="eyebrow">ADMIN / FEATURE 09</p><h2>Call the network</h2></div><a class="btn small" href="admin.php?user_id=<?= e($adminId) ?>">Admin home</a></div>
<div class="grid grid-2"><section class="table-wrap"><table><thead><tr><th>Patient</th><th>Information</th><th>Phone</th></tr></thead><tbody><?php foreach ($patientContacts as $contact): ?><tr><td><strong><?= e($contact['first_name'] . ' ' . $contact['last_name']) ?></strong><br><span class="mono">@<?= e($contact['username']) ?> / <?= e($contact['user_id']) ?></span></td><td><?= e($contact['emergency_type']) ?> / age <?= (int) $contact['age'] ?></td><td><?= phone_contact($contact['phone'] ?? null) ?></td></tr><?php endforeach; ?></tbody></table></section><section class="table-wrap"><table><thead><tr><th>Driver</th><th>Information</th><th>Phone</th></tr></thead><tbody><?php foreach ($driverContacts as $contact): ?><tr><td><strong><?= e($contact['first_name'] . ' ' . $contact['last_name']) ?></strong><br><span class="mono">@<?= e($contact['username']) ?> / <?= e($contact['user_id']) ?></span></td><td>License <?= e($contact['license_no']) ?><br><?= number_format((float) $contact['rating'], 2) ?> / 5</td><td><?= phone_contact($contact['phone'] ?? null) ?></td></tr><?php endforeach; ?></tbody></table></section></div>
<?php layout_footer(); ?>
