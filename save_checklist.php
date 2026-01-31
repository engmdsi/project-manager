<?php
require 'db.php';

$cid     = (int)($_POST['cid'] ?? 0);
$checked = (int)($_POST['checked'] ?? 0);

if ($cid) {
    $pdo->prepare("UPDATE checklists SET is_checked = ? WHERE id = ?")->execute([$checked, $cid]);
}