<?php
require_once __DIR__ . '/db.php';
$patientId = require_role('patient');

$searchType = trim((string) ($_GET['vehicle_type'] ?? 'Basic'));
if (!in_array($searchType, ['Basic', 'ICU'], true)) $searchType = 'Basic';
$searchResults = [];

if (isset($_GET['search'])) {
    $safeSearchType = mysqli_real_escape_string($conn, $searchType);
    $sql = "SELECT reg_no, vehicle_type, available_status FROM AMBULANCE WHERE available_status = 'Available' AND vehicle_type = '$safeSearchType' ORDER BY reg_no";
    $result = mysqli_query($conn, $sql);
    if (!$result) die('Ambulance search failed: ' . mysqli_error($conn));
    if (mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
            $searchResults[] = $row;
        }
    }
}

layout_header('Find an ambulance', '', $patientId);
?>
<div class="section-heading"><div><p class="eyebrow">PATIENT / FEATURE 01</p><h2>Find an ambulance</h2></div><a class="btn small" href="patient.php?user_id=<?= e($patientId) ?>">Patient home</a></div>
<section class="panel">
    <form method="get" class="form-grid">
        <input type="hidden" name="user_id" value="<?= e($patientId) ?>">
        <input type="hidden" name="search" value="1">
        <div><label for="vehicle_type">Ambulance class</label><select id="vehicle_type" name="vehicle_type"><option value="Basic" <?= $searchType === 'Basic' ? 'selected' : '' ?>>Basic — ৳1,500</option><option value="ICU" <?= $searchType === 'ICU' ? 'selected' : '' ?>>ICU — ৳8,000</option></select></div>
        <div class="field-full form-actions"><button class="btn teal" type="submit">Scan available fleet ↗</button></div>
    </form>
    <?php if (isset($_GET['search'])): ?>
        <hr style="border:0;border-top:3px solid var(--ink);margin:24px 0">
        <?php if (!$searchResults): ?><div class="empty">No <?= e($searchType) ?> ambulance is currently available.</div><?php else: ?>
            <div class="grid grid-3">
                <?php foreach ($searchResults as $vehicle): ?><div class="card"><p class="eyebrow">AVAILABLE VEHICLE</p><h3><?= e($vehicle['reg_no']) ?></h3><p class="mono"><?= e($vehicle['vehicle_type']) ?> · <?= e($vehicle['available_status']) ?></p></div><?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>
<?php layout_footer(); ?>
