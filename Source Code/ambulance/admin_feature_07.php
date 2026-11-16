<?php
require_once __DIR__ . '/db.php';
$adminId = require_role('admin');
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = trim((string) ($_POST['action'] ?? 'deploy'));

    if ($action === 'delete') {
        $regNo = strtoupper(trim((string) ($_POST['reg_no'] ?? '')));
        $safeRegNo = mysqli_real_escape_string($conn, $regNo);
        $safeAdminId = mysqli_real_escape_string($conn, $adminId);
        $sql = "SELECT available_status FROM AMBULANCE WHERE reg_no = '$safeRegNo' AND admin_id = '$safeAdminId'";
        $result = mysqli_query($conn, $sql);
        if (!$result) die('Ambulance lookup failed: ' . mysqli_error($conn));
        $toDelete = mysqli_num_rows($result) > 0 ? mysqli_fetch_array($result, MYSQLI_ASSOC) : false;
        if (!$toDelete) {
            $error = 'Ambulance not found.';
        } elseif ($toDelete['available_status'] === 'Busy') {
            $error = 'Cannot delete an ambulance that is currently on a ride.';
        } else {
            $sql = "DELETE FROM AMBULANCE WHERE reg_no = '$safeRegNo' AND admin_id = '$safeAdminId'";
            $deleteResult = mysqli_query($conn, $sql);
            if ($deleteResult && mysqli_affected_rows($conn) === 1) {
                flash('success', $regNo . ' removed from the fleet.');
                redirect('admin_feature_07.php?user_id=' . rawurlencode($adminId));
            } else {
                $error = 'Could not delete ' . $regNo . '. It may have ride history linked to it.';
            }
        }
    } else {
        $vehicleType = trim((string) ($_POST['vehicle_type'] ?? ''));
        $adminCost = filter_var($_POST['admin_cost'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000000]]);

        $stretcherOptions = ['Hydraulic foldable', 'Manual scoop', 'Electric stretcher'];
        $kitOptions = ['A', 'B', 'C'];
        $stretcherType = trim((string) ($_POST['stretcher_type'] ?? 'Hydraulic foldable'));
        $kitClass = strtoupper(trim((string) ($_POST['kit_class'] ?? 'A')));
        $ventilatorCount = filter_var($_POST['ventilator_count'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 50]]);
        $hasOxygen = ($_POST['has_oxygen'] ?? '0') === '1' ? 1 : 0;
        $passengerFare = $vehicleType === 'Basic' ? 1500 : ($vehicleType === 'ICU' ? 8000 : false);
        $errors = [];
        if (!in_array($vehicleType, ['Basic', 'ICU'], true)) $errors[] = 'Choose Basic or ICU.';
        if ($adminCost === false || $passengerFare === false || $adminCost >= $passengerFare) $errors[] = 'Admin cost must be lower than the fixed passenger fare.';
        if ($vehicleType === 'Basic' && (!in_array($stretcherType, $stretcherOptions, true) || !in_array($kitClass, $kitOptions, true))) $errors[] = 'Choose a valid Basic stretcher and kit class.';
        if ($vehicleType === 'ICU' && $ventilatorCount === false) $errors[] = 'Enter a valid ventilator count.';

        if (!$errors) {
            if (!mysqli_begin_transaction($conn)) {
                $error = 'The ambulance transaction could not start.';
            } else {
                $prefix = $vehicleType === 'Basic' ? 'AMB-B' : 'AMB-I';
                do {
                    $regNo = $prefix . random_int(100, 999);
                    $safeRegNo = mysqli_real_escape_string($conn, $regNo);
                    $sql = "SELECT EXISTS (SELECT 1 FROM AMBULANCE WHERE reg_no = '$safeRegNo') AS reg_exists";
                    $result = mysqli_query($conn, $sql);
                    if (!$result) die('Ambulance registration check failed: ' . mysqli_error($conn));
                    $regRow = mysqli_fetch_array($result, MYSQLI_ASSOC);
                } while ((int) $regRow['reg_exists'] === 1);

                $safeVehicleType = mysqli_real_escape_string($conn, $vehicleType);
                $safeAdminId = mysqli_real_escape_string($conn, $adminId);
                $sql = "INSERT INTO AMBULANCE (reg_no, hourly_rate, admin_cost, available_status, vehicle_type, admin_id) VALUES ('$safeRegNo', $passengerFare, $adminCost, 'Available', '$safeVehicleType', '$safeAdminId')";
                $ambulanceResult = mysqli_query($conn, $sql);
                $ambulanceOk = $ambulanceResult && mysqli_affected_rows($conn) === 1;
                if ($vehicleType === 'Basic') {
                    $safeStretcher = mysqli_real_escape_string($conn, $stretcherType);
                    $safeKit = mysqli_real_escape_string($conn, $kitClass);
                    $sql = "INSERT INTO BASIC_AMBULANCE (reg_no, stretcher_type, kit_class) VALUES ('$safeRegNo', '$safeStretcher', '$safeKit')";
                } else {
                    $sql = "INSERT INTO ICU_AMBULANCE (reg_no, ventilator_count, has_oxygen) VALUES ('$safeRegNo', $ventilatorCount, $hasOxygen)";
                }
                $subtypeResult = mysqli_query($conn, $sql);
                $subtypeOk = $subtypeResult && mysqli_affected_rows($conn) === 1;
                if (!$ambulanceOk || !$subtypeOk) $error = 'The ambulance details could not be saved.';
                if ($error === '') {
                    if (mysqli_commit($conn)) {
                        flash('success', $regNo . ' saved. Passenger fare: ৳' . number_format((int) $passengerFare) . '; admin cost: ৳' . number_format((int) $adminCost) . '.');
                        redirect('admin_feature_07.php?user_id=' . rawurlencode($adminId));
                    } else {
                        $error = 'The ambulance transaction could not be completed.';
                        mysqli_rollback($conn);
                    }
                } else {
                    mysqli_rollback($conn);
                }
            }
        } else {
            $error = implode(' ', $errors);
        }
    }
}

$safeAdminId = mysqli_real_escape_string($conn, $adminId);

$sql = "SELECT a.reg_no, a.vehicle_type, a.hourly_rate, a.admin_cost, a.available_status, b.stretcher_type, b.kit_class, i.ventilator_count, i.has_oxygen FROM AMBULANCE a LEFT JOIN BASIC_AMBULANCE b ON a.reg_no = b.reg_no LEFT JOIN ICU_AMBULANCE i ON a.reg_no = i.reg_no WHERE a.admin_id = '$safeAdminId' ORDER BY a.vehicle_type, a.reg_no";
$result = mysqli_query($conn, $sql);
if (!$result) die('Fleet inventory query failed: ' . mysqli_error($conn));
$inventory = [];
if (mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
        $inventory[] = $row;
    }
}
$sql = "SELECT COUNT(*) AS total_rides FROM RIDE_REQUEST WHERE admin_id = '$safeAdminId'";
$result = mysqli_query($conn, $sql);
if (!$result) die('Ride count query failed: ' . mysqli_error($conn));
$counts = mysqli_fetch_array($result, MYSQLI_ASSOC);
$sql = "SELECT COALESCE(SUM(ac.admin_cost), 0) AS admin_earnings FROM ACCEPTS ac INNER JOIN RIDE_REQUEST r ON r.ride_id = ac.ride_id WHERE r.status = 'Completed' AND ac.settlement_status = 'Settled' AND r.admin_id = '$safeAdminId'";
$result = mysqli_query($conn, $sql);
if (!$result) die('Admin earnings query failed: ' . mysqli_error($conn));
$adminEarningsRow = mysqli_fetch_array($result, MYSQLI_ASSOC);
$adminEarnings = (int) ($adminEarningsRow['admin_earnings'] ?? 0);
$sql = "SELECT COALESCE(SUM(ac.driver_earning), 0) AS driver_earnings FROM ACCEPTS ac INNER JOIN RIDE_REQUEST r ON r.ride_id = ac.ride_id WHERE r.status = 'Completed' AND ac.settlement_status = 'Settled' AND r.admin_id = '$safeAdminId'";
$result = mysqli_query($conn, $sql);
if (!$result) die('Driver earnings query failed: ' . mysqli_error($conn));
$driverEarningsRow = mysqli_fetch_array($result, MYSQLI_ASSOC);
$driverEarnings = (int) ($driverEarningsRow['driver_earnings'] ?? 0);

layout_header('Fleet performance', '', $adminId);
?>
<div class="section-heading"><div><p class="eyebrow">ADMIN / FEATURE 07</p><h2>Fleet management</h2></div><a class="btn small" href="admin.php?user_id=<?= e($adminId) ?>">Admin home</a></div>
<?php if ($error !== ''): ?><div class="alert error" role="alert"><?= e($error) ?></div><?php endif; ?>
<div class="stats"><div class="stat"><span class="stat-label">All rides</span><span class="stat-value"><?= (int) ($counts['total_rides'] ?? 0) ?></span></div><div class="stat"><span class="stat-label">Driver earnings</span><span class="stat-value">৳<?= number_format($driverEarnings) ?></span></div><div class="stat"><span class="stat-label">Admin earnings</span><span class="stat-value">৳<?= number_format($adminEarnings) ?></span></div></div>
<section class="panel pink"><form method="post" class="form-grid"><?= csrf_field() ?><div><label for="management_vehicle_type">Vehicle type</label><select id="management_vehicle_type" name="vehicle_type"><option value="Basic">Basic — fare ৳1,500</option><option value="ICU">ICU — fare ৳8,000</option></select></div><div><label for="management_admin_cost">Admin cost</label><input id="management_admin_cost" name="admin_cost" type="number" min="1" max="7999" value="500" required></div><div id="basic-fields"><label for="stretcher_type">Stretcher type</label><select id="stretcher_type" name="stretcher_type"><option value="Hydraulic foldable">Hydraulic foldable</option><option value="Manual scoop">Manual scoop</option><option value="Electric stretcher">Electric stretcher</option></select></div><div id="basic-kit-field"><label for="kit_class">Kit class</label><select id="kit_class" name="kit_class"><option value="A">Class A</option><option value="B">Class B</option><option value="C">Class C</option></select></div><div id="icu-fields"><label for="ventilator_count">Ventilator count</label><input id="ventilator_count" name="ventilator_count" type="number" min="0" max="50" value="2"></div><div id="oxygen-field"><label for="has_oxygen">Oxygen available</label><select id="has_oxygen" name="has_oxygen"><option value="1">Yes</option><option value="0">No</option></select></div><div class="field-full form-actions"><button class="btn" type="submit">Deploy ambulance ↗</button></div></form></section>

<section class="table-wrap" style="margin-top:24px"><table><thead><tr><th>Registration</th><th>Class</th><th>Ride charge</th><th>Admin cost</th><th>Status</th><th>Subtype details</th><th>Action</th></tr></thead><tbody><?php foreach ($inventory as $vehicle): ?><tr><td class="mono"><?= e($vehicle['reg_no']) ?></td><td><?= e($vehicle['vehicle_type']) ?></td><td>৳<?= number_format((int) $vehicle['hourly_rate']) ?></td><td>৳<?= number_format((int) $vehicle['admin_cost']) ?></td><td><span class="badge <?= $vehicle['available_status'] === 'Available' ? 'completed' : ($vehicle['available_status'] === 'Busy' ? 'requested' : 'cancelled') ?>"><?= e($vehicle['available_status']) ?></span></td><td><?php if ($vehicle['vehicle_type'] === 'Basic'): ?><?= e($vehicle['stretcher_type']) ?> / kit <?= e($vehicle['kit_class']) ?><?php else: ?><?= (int) $vehicle['ventilator_count'] ?> ventilators / <?= $vehicle['has_oxygen'] ? 'oxygen ready' : 'no oxygen' ?><?php endif; ?></td><td><?php if ($vehicle['available_status'] !== 'Busy'): ?><form method="post" class="inline-form"><input type="hidden" name="action" value="delete"><input type="hidden" name="reg_no" value="<?= e($vehicle['reg_no']) ?>"><?= csrf_field() ?><button class="btn small cancelled" type="submit" onclick="return confirm('Remove <?= e($vehicle['reg_no']) ?> from the fleet?')">Delete</button></form><?php else: ?><span class="mono">On a ride</span><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></section>
<script>
const managementType = document.getElementById('management_vehicle_type');
const managementCost = document.getElementById('management_admin_cost');
const basicFields = [document.getElementById('basic-fields'), document.getElementById('basic-kit-field')];
const icuFields = [document.getElementById('icu-fields'), document.getElementById('oxygen-field')];
function syncManagementType() { const isBasic = managementType.value === 'Basic'; managementCost.value = isBasic ? 500 : 6000; basicFields.forEach((field) => field.hidden = !isBasic); icuFields.forEach((field) => field.hidden = isBasic); }
managementType.addEventListener('change', syncManagementType);
syncManagementType();
</script>
<?php layout_footer(); ?>
