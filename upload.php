<?php
require_once 'config.php';

// تنظیم هدر JSON
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file']) && isset($_POST['task_id'])) {
    $task_id = (int)$_POST['task_id'];
    $file = $_FILES['file'];
    
    // بررسی خطاهای آپلود
    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'error' => 'خطا در آپلود فایل']);
        exit;
    }
    
    // بررسی حجم فایل (حداکثر 50MB)
    $max_size = 50 * 1024 * 1024;
    if ($file['size'] > $max_size) {
        echo json_encode(['success' => false, 'error' => 'حجم فایل نباید بیشتر از 50MB باشد']);
        exit;
    }
    
    // ایجاد پوشه آپلود اگر وجود ندارد
    if (!file_exists(UPLOAD_PATH)) {
        mkdir(UPLOAD_PATH, 0755, true);
    }
    
    // تولید نام امن برای فایل
    $original_name = basename($file['name']);
    $extension = pathinfo($original_name, PATHINFO_EXTENSION);
    $safe_filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', pathinfo($original_name, PATHINFO_FILENAME));
    $filename = $safe_filename . '_' . time() . '.' . $extension;
    $filepath = UPLOAD_PATH . $filename;
    
    // جلوگیری از overwrite فایل‌های موجود
    $counter = 1;
    while (file_exists($filepath)) {
        $filename = $safe_filename . '_' . time() . '_' . $counter . '.' . $extension;
        $filepath = UPLOAD_PATH . $filename;
        $counter++;
    }
    
    // آپلود فایل
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        // تولید لینک کوتاه
        $short_url = generate_short_url();
        
        try {
            // ذخیره اطلاعات در دیتابیس
            $stmt = $pdo->prepare("INSERT INTO files (task_id, filename, original_name, short_url) VALUES (?, ?, ?, ?)");
            $stmt->execute([$task_id, $filename, $original_name, $short_url]);
            
            echo json_encode([
                'success' => true,
                'message' => 'فایل با موفقیت آپلود شد',
                'file' => [
                    'name' => $original_name,
                    'url' => SITE_URL . UPLOAD_PATH . $filename,
                    'short_url' => $short_url
                ]
            ]);
        } catch(PDOException $e) {
            // حذف فایل در صورت خطای دیتابیس
            unlink($filepath);
            echo json_encode(['success' => false, 'error' => 'خطا در ذخیره اطلاعات فایل']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'خطا در انتقال فایل']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'درخواست نامعتبر']);
}
?>