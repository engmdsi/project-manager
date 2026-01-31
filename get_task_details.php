<?php
require_once 'config.php';

header('Content-Type: application/json');

if (isset($_GET['task_id'])) {
    $task_id = (int)$_GET['task_id'];
    
    try {
        // دریافت اطلاعات کار
        $stmt = $pdo->prepare("SELECT t.*, p.name as project_name FROM tasks t LEFT JOIN projects p ON t.project_id = p.id WHERE t.id = ?");
        $stmt->execute([$task_id]);
        $task = $stmt->fetch();
        
        if ($task) {
            // دریافت چک‌لیست‌ها
            $checklist_stmt = $pdo->prepare("SELECT * FROM checklists WHERE task_id = ? ORDER BY id");
            $checklist_stmt->execute([$task_id]);
            $checklists = $checklist_stmt->fetchAll();
            
            // دریافت فایل‌ها
            $files_stmt = $pdo->prepare("SELECT * FROM files WHERE task_id = ? ORDER BY uploaded_at DESC");
            $files_stmt->execute([$task_id]);
            $files = $files_stmt->fetchAll();
            
            // تولید HTML
            $html = '
            <div class="task-details">
                <div style="margin-bottom: 15px;">
                    <strong>پروژه:</strong> ' . htmlspecialchars($task['project_name']) . '<br>
                    <strong>تاریخ:</strong> ' . show_jalali_date($task['task_date']) . '<br>
                    <strong>ساعت:</strong> ' . substr($task['task_time'], 0, 5) . '<br>
                    <strong>اولویت:</strong> ' . ($task['priority'] == 1 ? 'عادی' : ($task['priority'] == 2 ? 'متوسط' : 'فوری')) . '<br>
                    <strong>پیشرفت:</strong> ' . $task['progress'] . '%
                </div>
                
                <div style="margin-bottom: 15px;">
                    <strong>شرح کار:</strong>
                    <p style="background: #f8f9fa; padding: 10px; border-radius: 5px; margin-top: 5px;">' . nl2br(htmlspecialchars($task['description'])) . '</p>
                </div>';
            
            // چک‌لیست‌ها
            if (count($checklists) > 0) {
                $html .= '
                <div style="margin-bottom: 15px;">
                    <strong>چک‌لیست:</strong>
                    <div style="margin-top: 5px;">';
                
                foreach ($checklists as $checklist) {
                    $checked = $checklist['is_checked'] ? '✅' : '◻️';
                    $html .= '<div style="display: flex; align-items: center; gap: 10px; margin-bottom: 5px;">
                                <span>' . $checked . '</span>
                                <span>' . htmlspecialchars($checklist['item']) . '</span>
                              </div>';
                }
                
                $html .= '</div></div>';
            }
            
            // فایل‌ها
            if (count($files) > 0) {
                $html .= '
                <div style="margin-bottom: 15px;">
                    <strong>فایل‌ها:</strong>
                    <div style="margin-top: 5px;">';
                
                foreach ($files as $file) {
                    $html .= '<div style="display: flex; justify-content: space-between; align-items: center; padding: 8px; background: #e8f4fc; border-radius: 5px; margin-bottom: 5px;">
                                <span>' . htmlspecialchars($file['original_name']) . '</span>
                                <a href="uploads/' . $file['filename'] . '" target="_blank" style="color: #3498db; text-decoration: none;">
                                    <i class="fas fa-download"></i> دانلود
                                </a>
                              </div>';
                }
                
                $html .= '</div></div>';
            }
            
            $html .= '
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <a href="edit_task.php?id=' . $task['id'] . '" class="btn-small btn-info" style="text-decoration: none;">
                        <i class="fas fa-edit"></i> ویرایش کار
                    </a>
                    <button onclick="completeTask(' . $task['id'] . '); closeTaskModal();" class="btn-small btn-success">
                        <i class="fas fa-check"></i> تکمیل کار
                    </button>
                </div>
            </div>';
            
            echo json_encode([
                'success' => true,
                'task' => $task,
                'html' => $html
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'کار یافت نشد']);
        }
        
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'شناسه کار مشخص نشده']);
}
?>