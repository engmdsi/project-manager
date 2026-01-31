<?php
require 'db.php';

$task_id = (int)($_POST['task_id'] ?? 0);
$status  = $_POST['status'] ?? '';

if ($task_id && in_array($status, ['todo','doing','done'])) {
    $pdo->prepare("UPDATE tasks SET status = ? WHERE id = ?")->execute([$status, $task_id]);
}