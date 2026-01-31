<?php
require_once 'config.php';

// ایجاد جداول با فیلد completed
$sql = "
CREATE TABLE IF NOT EXISTS projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    progress INT DEFAULT 0,
    priority INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

CREATE TABLE IF NOT EXISTS tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    task_date DATE,
    task_time TIME,
    progress INT DEFAULT 0,
    priority INT DEFAULT 1,
    completed TINYINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

CREATE TABLE IF NOT EXISTS files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    short_url VARCHAR(50) NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

CREATE TABLE IF NOT EXISTS checklists (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    item TEXT NOT NULL,
    is_checked BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;
";

try {
    $pdo->exec($sql);
    
    // اگر فیلد completed وجود ندارد، آن را اضافه کنیم
    $check = $pdo->query("SHOW COLUMNS FROM tasks LIKE 'completed'");
    if($check->rowCount() == 0) {
        $pdo->exec("ALTER TABLE tasks ADD COLUMN completed TINYINT DEFAULT 0");
    }
    
    echo "<!DOCTYPE html>
    <html lang='fa' dir='rtl'>
    <head>
        <meta charset='UTF-8'>
        <title>نصب سیستم</title>
        <style>
            body { 
                font-family: 'Vazirmatn', sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
                margin: 0;
            }
            .message-box {
                background: white;
                padding: 40px;
                border-radius: 20px;
                box-shadow: 0 15px 35px rgba(0,0,0,0.2);
                text-align: center;
                max-width: 500px;
            }
            .success {
                color: #2ecc71;
                font-size: 2rem;
                margin-bottom: 20px;
            }
            .btn {
                display: inline-block;
                padding: 12px 30px;
                background: #3498db;
                color: white;
                text-decoration: none;
                border-radius: 50px;
                margin-top: 20px;
                font-weight: bold;
                transition: all 0.3s;
            }
            .btn:hover {
                background: #2980b9;
                transform: translateY(-3px);
            }
        </style>
    </head>
    <body>
        <div class='message-box'>
            <div class='success'>✅</div>
            <h1>نصب با موفقیت انجام شد!</h1>
            <p>سیستم مدیریت پروژه آماده استفاده است.</p>
            <a href='index.php' class='btn'>ورود به سیستم</a>
        </div>
    </body>
    </html>";
} catch(PDOException $e) {
    echo "<!DOCTYPE html>
    <html lang='fa' dir='rtl'>
    <head>
        <meta charset='UTF-8'>
        <title>خطا در نصب</title>
        <style>
            body {
                font-family: 'Vazirmatn', sans-serif;
                background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
                margin: 0;
            }
            .message-box {
                background: white;
                padding: 40px;
                border-radius: 20px;
                box-shadow: 0 15px 35px rgba(0,0,0,0.2);
                text-align: center;
                max-width: 500px;
            }
            .error {
                color: #e74c3c;
                font-size: 2rem;
                margin-bottom: 20px;
            }
        </style>
    </head>
    <body>
        <div class='message-box'>
            <div class='error'>❌</div>
            <h1>خطا در نصب</h1>
            <p>" . $e->getMessage() . "</p>
            <p>لطفاً تنظیمات دیتابیس را در فایل config.php بررسی کنید.</p>
        </div>
    </body>
    </html>";
}
?>