<?php
require_once 'config.php';

if (isset($_POST['task_id']) && isset($_POST['action'])) {
    $task_id = $_POST['task_id'];
    $action = $_POST['action'];
    
    try {
        if ($action === 'move' && isset($_POST['container'])) {
            // در اینجا می‌توانید منطق جابجایی را پیاده‌سازی کنید
            // مثلاً ذخیره کانتینر جدید برای کار
            echo json_encode(['success' => true]);
        } elseif ($action === 'update_progress') {
            $progress = $_POST['progress'] ?? 0;
            $stmt = $pdo->prepare("UPDATE tasks SET progress = ? WHERE id = ?");
            $stmt->execute([$progress, $task_id]);
            echo json_encode(['success' => true]);
        }
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>