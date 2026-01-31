<?php
require_once 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'complete';
    
    try {
        if ($action === 'complete' && isset($_POST['task_id'])) {
            $task_id = (int)$_POST['task_id'];
            $stmt = $pdo->prepare("UPDATE tasks SET completed = 1 WHERE id = ?");
            $stmt->execute([$task_id]);
            
        } elseif ($action === 'uncomplete' && isset($_POST['task_id'])) {
            $task_id = (int)$_POST['task_id'];
            $stmt = $pdo->prepare("UPDATE tasks SET completed = 0 WHERE id = ?");
            $stmt->execute([$task_id]);
            
        } elseif ($action === 'uncomplete_all') {
            $stmt = $pdo->prepare("UPDATE tasks SET completed = 0 WHERE completed = 1");
            $stmt->execute();
        }
        
        echo json_encode(['success' => true]);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'درخواست نامعتبر']);
}
?>