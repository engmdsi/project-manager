<?php
require_once 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'single';
    
    try {
        if ($action === 'single' && isset($_POST['task_id'])) {
            $task_id = (int)$_POST['task_id'];
            
            // حذف وابستگی‌ها
            $pdo->prepare("DELETE FROM checklists WHERE task_id = ?")->execute([$task_id]);
            
            // حذف فایل‌ها
            $files = $pdo->prepare("SELECT filename FROM files WHERE task_id = ?")->execute([$task_id]);
            $files = $files->fetchAll();
            
            foreach($files as $file) {
                if(file_exists(UPLOAD_PATH . $file['filename'])) {
                    unlink(UPLOAD_PATH . $file['filename']);
                }
            }
            
            $pdo->prepare("DELETE FROM files WHERE task_id = ?")->execute([$task_id]);
            
            // حذف کار
            $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ?");
            $stmt->execute([$task_id]);
            
        } elseif ($action === 'delete_all_completed') {
            // دریافت کارهای تکمیل شده
            $tasks = $pdo->query("SELECT id FROM tasks WHERE completed = 1")->fetchAll();
            
            foreach($tasks as $task) {
                $task_id = $task['id'];
                
                // حذف وابستگی‌ها
                $pdo->prepare("DELETE FROM checklists WHERE task_id = ?")->execute([$task_id]);
                
                // حذف فایل‌ها
                $files = $pdo->prepare("SELECT filename FROM files WHERE task_id = ?")->execute([$task_id]);
                $files = $files->fetchAll();
                
                foreach($files as $file) {
                    if(file_exists(UPLOAD_PATH . $file['filename'])) {
                        unlink(UPLOAD_PATH . $file['filename']);
                    }
                }
                
                $pdo->prepare("DELETE FROM files WHERE task_id = ?")->execute([$task_id]);
            }
            
            // حذف همه کارهای تکمیل شده
            $stmt = $pdo->prepare("DELETE FROM tasks WHERE completed = 1");
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