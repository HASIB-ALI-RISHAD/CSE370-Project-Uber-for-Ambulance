<?php
require_once __DIR__ . '/db.php';
$userId = clean_id((string) ($_GET['user_id'] ?? $_SESSION['user_id'] ?? ''));
$target = 'patient.php' . ($userId !== '' ? '?user_id=' . rawurlencode($userId) : '');
redirect($target);
