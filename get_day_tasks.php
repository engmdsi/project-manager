<?php
require_once 'config.php';

if (isset($_POST['date'])) {
    $date = $_POST['date'];
    
    $stmt = $pdo->prepare("
        SELECT t.*, p.name as project_name 
        FROM tasks t 
        LEFT JOIN projects p ON t.project_id = p.id 
        WHERE t.task_date = ? 
        ORDER BY t.task_time
    ");
    $stmt->execute([$date]);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode(['tasks' => $tasks]);
}
?>