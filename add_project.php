<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $progress = $_POST['progress'] ?? 0;
    $priority = $_POST['priority'] ?? 1;
    
    try {
        $stmt = $pdo->prepare("INSERT INTO projects (name, progress, priority) VALUES (?, ?, ?)");
        $stmt->execute([$name, $progress, $priority]);
        
        header('Location: index.php');
        exit;
    } catch(PDOException $e) {
        $error = "خطا در ایجاد پروژه: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ایجاد پروژه جدید</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1><i class="fas fa-project-diagram"></i> ایجاد پروژه جدید</h1>
            <div class="header-actions">
                <a href="index.php" class="btn btn-info"><i class="fas fa-home"></i> صفحه اصلی</a>
            </div>
        </header>
        
        <div class="form-container">
            <?php if(isset($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="name"><i class="fas fa-heading"></i> نام پروژه</label>
                    <input type="text" id="name" name="name" class="form-control" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="progress"><i class="fas fa-chart-line"></i> درصد پیشرفت</label>
                        <input type="range" id="progress" name="progress" class="form-control" min="0" max="100" value="0">
                        <div class="range-value" id="progressValue">0%</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="priority"><i class="fas fa-flag"></i> اولویت</label>
                        <select id="priority" name="priority" class="form-control">
                            <option value="1">عادی</option>
                            <option value="2">متوسط</option>
                            <option value="3">فوری</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-success btn-block">
                        <i class="fas fa-check"></i> ایجاد پروژه
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        const progressSlider = document.getElementById('progress');
        const progressValue = document.getElementById('progressValue');
        
        progressSlider.addEventListener('input', function() {
            progressValue.textContent = this.value + '%';
        });
    </script>
</body>
</html>