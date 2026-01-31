<?php
require_once 'config.php';

$response = ['installed' => false];

try {
    $check = $pdo->query("SHOW TABLES LIKE 'projects'");
    if($check->rowCount() > 0) {
        $response['installed'] = true;
    }
} catch(Exception $e) {
    $response['error'] = $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($response);
?>