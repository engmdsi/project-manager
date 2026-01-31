<?php
require_once 'config.php';

$task_id = $_GET['id'] ?? 0;

if (!$task_id) {
    header('Location: index.php');
    exit;
}

// دریافت اطلاعات کار
$task_stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
$task_stmt->execute([$task_id]);
$task = $task_stmt->fetch();

if (!$task) {
    header('Location: index.php');
    exit;
}

// دریافت پروژه‌ها
$projects_stmt = $pdo->query("SELECT * FROM projects ORDER BY name");
$projects = $projects_stmt->fetchAll();

// دریافت چک‌لیست‌ها - رفع خطا در اینجا
$checklist_stmt = $pdo->prepare("SELECT * FROM checklists WHERE task_id = ? ORDER BY id");
$checklist_stmt->execute([$task_id]);
$checklists = $checklist_stmt->fetchAll();

// دریافت فایل‌ها - رفع خطا در اینجا
$files_stmt = $pdo->prepare("SELECT * FROM files WHERE task_id = ? ORDER BY uploaded_at DESC");
$files_stmt->execute([$task_id]);
$files = $files_stmt->fetchAll();

// تبدیل تاریخ میلادی به شمسی
$task_date = explode('-', $task['task_date']);
$jalali_date = gregorian_to_jalali($task_date[0], $task_date[1], $task_date[2]);
$task_date_shamsi = $jalali_date[0] . '/' . str_pad($jalali_date[1], 2, '0', STR_PAD_LEFT) . '/' . str_pad($jalali_date[2], 2, '0', STR_PAD_LEFT);

// پردازش فرم ویرایش
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $project_id = $_POST['project_id'] ?? '';
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $task_date = $_POST['task_date'] ?? '';
    $task_time = $_POST['task_time'] ?? '';
    $progress = $_POST['progress'] ?? 0;
    $priority = $_POST['priority'] ?? 1;
    
    // تبدیل تاریخ شمسی به میلادی
    $date_parts = explode('/', $task_date);
    if(count($date_parts) === 3) {
        list($year, $month, $day) = $date_parts;
        $gregorian = jalali_to_gregorian($year, $month, $day);
        $gregorian_date = sprintf("%04d-%02d-%02d", $gregorian[0], $gregorian[1], $gregorian[2]);
    } else {
        $gregorian_date = date('Y-m-d');
    }
    
    try {
        $stmt = $pdo->prepare("
            UPDATE tasks SET 
            project_id = ?, 
            title = ?, 
            description = ?, 
            task_date = ?, 
            task_time = ?, 
            progress = ?, 
            priority = ? 
            WHERE id = ?
        ");
        $stmt->execute([$project_id, $title, $description, $gregorian_date, $task_time, $progress, $priority, $task_id]);
        
        // به‌روزرسانی چک‌لیست‌ها
        if(isset($_POST['checklist_id'])) {
            foreach($_POST['checklist_id'] as $index => $checklist_id) {
                $item = $_POST['checklist_item'][$index] ?? '';
                $is_checked = isset($_POST['checklist_checked'][$index]) ? 1 : 0;
                
                if($checklist_id > 0) {
                    $check_stmt = $pdo->prepare("UPDATE checklists SET item = ?, is_checked = ? WHERE id = ?");
                    $check_stmt->execute([$item, $is_checked, $checklist_id]);
                } else {
                    if(!empty(trim($item))) {
                        $check_stmt = $pdo->prepare("INSERT INTO checklists (task_id, item, is_checked) VALUES (?, ?, ?)");
                        $check_stmt->execute([$task_id, trim($item), $is_checked]);
                    }
                }
            }
        }
        
        header('Location: index.php?msg=task_updated');
        exit;
    } catch(PDOException $e) {
        $error = "خطا در به‌روزرسانی کار: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ویرایش کار</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        /* استایل‌های اضافی برای آپلود */
        .upload-area {
            border: 3px dashed #3498db;
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            background: #f8f9fa;
            cursor: pointer;
            transition: all 0.3s;
            margin: 20px 0;
        }
        
        .upload-area:hover {
            border-color: #2ecc71;
            background: #e8f5e9;
        }
        
        .upload-area i {
            font-size: 48px;
            color: #3498db;
            margin-bottom: 15px;
        }
        
        .file-list {
            margin-top: 20px;
        }
        
        .file-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: white;
            border-radius: 10px;
            margin-bottom: 10px;
            border: 1px solid #e0e0e0;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .file-icon {
            font-size: 24px;
            color: #3498db;
        }
        
        .file-info {
            flex-grow: 1;
        }
        
        .file-name {
            font-weight: bold;
            margin-bottom: 5px;
            color: #2c3e50;
        }
        
        .file-meta {
            color: #666;
            font-size: 0.9rem;
        }
        
        .short-url {
            background: #e8f4fc;
            padding: 8px 12px;
            border-radius: 6px;
            font-family: monospace;
            margin-top: 5px;
            font-size: 0.9rem;
            word-break: break-all;
        }
        
        .upload-progress {
            height: 20px;
            background: #ecf0f1;
            border-radius: 10px;
            margin: 15px 0;
            overflow: hidden;
            display: none;
        }
        
        .progress-bar {
            height: 100%;
            background: linear-gradient(to right, #4facfe, #00f2fe);
            border-radius: 10px;
            width: 0%;
            transition: width 0.3s;
        }
        
        .calendar-popup {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            z-index: 10000;
            width: 320px;
            display: none;
        }
        
        .calendar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 9999;
            display: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1><i class="fas fa-edit"></i> ویرایش کار</h1>
            <div class="header-actions">
                <a href="index.php" class="btn btn-info"><i class="fas fa-home"></i> صفحه اصلی</a>
            </div>
        </header>
        
        <div class="form-container">
            <?php if(isset($error)): ?>
            <div class="alert alert-error">
                <?php echo $error; ?>
            </div>
            <?php endif; ?>
            
            <?php if(isset($_GET['msg']) && $_GET['msg'] == 'task_updated'): ?>
            <div class="alert alert-success">
                ✅ تغییرات با موفقیت ذخیره شد!
            </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="editTaskForm">
                <div class="form-row">
                    <div class="form-group">
                        <label for="project_id"><i class="fas fa-project-diagram"></i> پروژه مربوطه</label>
                        <select id="project_id" name="project_id" class="form-control" required>
                            <?php foreach($projects as $project): ?>
                            <option value="<?php echo $project['id']; ?>" <?php echo $project['id'] == $task['project_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($project['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="title"><i class="fas fa-heading"></i> عنوان کار</label>
                        <input type="text" id="title" name="title" class="form-control" value="<?php echo htmlspecialchars($task['title']); ?>" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="task_date"><i class="fas fa-calendar-alt"></i> تاریخ (شمسی)</label>
                        <div style="display: flex; gap: 10px;">
                            <input type="text" id="task_date" name="task_date" class="form-control" value="<?php echo $task_date_shamsi; ?>" readonly>
                            <button type="button" onclick="showCalendar()" class="btn btn-info" style="white-space: nowrap;">
                                <i class="fas fa-calendar"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="task_time"><i class="fas fa-clock"></i> ساعت</label>
                        <input type="time" id="task_time" name="task_time" class="form-control" value="<?php echo substr($task['task_time'], 0, 5); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="progress"><i class="fas fa-chart-line"></i> درصد پیشرفت</label>
                        <input type="range" id="progress" name="progress" class="form-control" min="0" max="100" value="<?php echo $task['progress']; ?>">
                        <div class="range-value" id="progressValue"><?php echo $task['progress']; ?>%</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="priority"><i class="fas fa-flag"></i> اولویت</label>
                        <select id="priority" name="priority" class="form-control">
                            <option value="1" <?php echo $task['priority'] == 1 ? 'selected' : ''; ?>>عادی</option>
                            <option value="2" <?php echo $task['priority'] == 2 ? 'selected' : ''; ?>>متوسط</option>
                            <option value="3" <?php echo $task['priority'] == 3 ? 'selected' : ''; ?>>فوری</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="description"><i class="fas fa-file-alt"></i> شرح کار</label>
                    <textarea id="description" name="description" class="form-control" rows="5"><?php echo htmlspecialchars($task['description']); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-check-square"></i> چک لیست</label>
                    <div id="checklistContainer">
                        <?php if(count($checklists) > 0): ?>
                            <?php foreach($checklists as $checklist): ?>
                            <div class="checklist-item">
                                <input type="hidden" name="checklist_id[]" value="<?php echo $checklist['id']; ?>">
                                <input type="checkbox" name="checklist_checked[]" <?php echo $checklist['is_checked'] ? 'checked' : ''; ?>>
                                <input type="text" name="checklist_item[]" value="<?php echo htmlspecialchars($checklist['item']); ?>">
                                <button type="button" class="btn-small btn-danger remove-checklist">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="checklist-item">
                                <input type="hidden" name="checklist_id[]" value="0">
                                <input type="checkbox" name="checklist_checked[]">
                                <input type="text" name="checklist_item[]" placeholder="عنوان آیتم چک لیست...">
                                <button type="button" class="btn-small btn-danger remove-checklist">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                    <button type="button" id="addChecklist" class="btn-small btn-success">
                        <i class="fas fa-plus"></i> افزودن آیتم
                    </button>
                </div>
                
                <!-- بخش آپلود فایل -->
                <div class="form-group">
                    <label><i class="fas fa-cloud-upload-alt"></i> آپلود فایل جدید</label>
                    <div class="upload-area" onclick="document.getElementById('fileInput').click()" id="uploadArea">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <h3>فایل را اینجا رها کنید یا کلیک کنید</h3>
                        <p>حداکثر حجم: 50MB</p>
                        <input type="file" id="fileInput" style="display: none;" onchange="handleFileSelect()">
                    </div>
                    
                    <div id="selectedFileInfo" style="display: none; margin: 15px 0;">
                        <div style="background: #e8f4fc; padding: 15px; border-radius: 10px;">
                            <strong>فایل انتخاب شده:</strong>
                            <div id="fileName"></div>
                            <div id="fileSize" style="font-size: 0.9rem; color: #666;"></div>
                        </div>
                    </div>
                    
                    <div class="upload-progress" id="uploadProgress">
                        <div class="progress-bar" id="progressBar"></div>
                    </div>
                    
                    <button type="button" onclick="uploadFile(<?php echo $task_id; ?>)" class="btn btn-success" id="uploadBtn" style="display: none;">
                        <i class="fas fa-upload"></i> آپلود فایل
                    </button>
                </div>
                
                <!-- فایل‌های آپلود شده -->
                <div class="form-group">
                    <label><i class="fas fa-file"></i> فایل‌های آپلود شده</label>
                    <div class="file-list" id="fileList">
                        <?php if(count($files) > 0): ?>
                            <?php foreach($files as $file): ?>
                            <div class="file-item">
                                <div class="file-icon">
                                    <i class="fas fa-file"></i>
                                </div>
                                <div class="file-info">
                                    <div class="file-name"><?php echo htmlspecialchars($file['original_name']); ?></div>
                                    <div class="file-meta">
                                        <?php echo date('Y/m/d H:i', strtotime($file['uploaded_at'])); ?>
                                    </div>
                                    <div class="short-url">
                                        لینک: <?php echo SITE_URL . 'uploads/' . $file['filename']; ?>
                                    </div>
                                </div>
                                <div>
                                    <a href="uploads/<?php echo $file['filename']; ?>" target="_blank" class="btn-small btn-info" title="دانلود">
                                        <i class="fas fa-download"></i>
                                    </a>
                                    <button type="button" onclick="copyToClipboard('<?php echo SITE_URL . 'uploads/' . $file['filename']; ?>')" class="btn-small btn-success" title="کپی لینک">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="text-align: center; color: #666; padding: 20px; background: #f8f9fa; border-radius: 10px;">
                                هنوز هیچ فایلی آپلود نشده است.
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-success btn-block">
                        <i class="fas fa-save"></i> ذخیره تغییرات
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Overlay و تقویم -->
    <div class="calendar-overlay" id="calendarOverlay" onclick="closeCalendar()"></div>
    <div class="calendar-popup" id="calendarPopup"></div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // کنترل اسلایدر پیشرفت
            const progressSlider = $('#progress');
            const progressValue = $('#progressValue');
            
            progressSlider.on('input', function() {
                progressValue.text(this.value + '%');
            });
            
            // مدیریت چک لیست
            $('#addChecklist').click(function() {
                const newItem = `
                    <div class="checklist-item">
                        <input type="hidden" name="checklist_id[]" value="0">
                        <input type="checkbox" name="checklist_checked[]">
                        <input type="text" name="checklist_item[]" placeholder="عنوان آیتم چک لیست...">
                        <button type="button" class="btn-small btn-danger remove-checklist">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                `;
                $('#checklistContainer').append(newItem);
            });
            
            $(document).on('click', '.remove-checklist', function() {
                $(this).closest('.checklist-item').remove();
            });
            
            // Drag & Drop برای آپلود
            const uploadArea = $('#uploadArea');
            
            uploadArea.on('dragover', function(e) {
                e.preventDefault();
                uploadArea.css({
                    'border-color': '#2ecc71',
                    'background': '#e8f5e9'
                });
            });
            
            uploadArea.on('dragleave', function() {
                uploadArea.css({
                    'border-color': '#3498db',
                    'background': '#f8f9fa'
                });
            });
            
            uploadArea.on('drop', function(e) {
                e.preventDefault();
                uploadArea.css({
                    'border-color': '#3498db',
                    'background': '#f8f9fa'
                });
                
                const files = e.originalEvent.dataTransfer.files;
                if(files.length) {
                    document.getElementById('fileInput').files = files;
                    handleFileSelect();
                }
            });
        });
        
        // مدیریت انتخاب فایل
        function handleFileSelect() {
            const fileInput = document.getElementById('fileInput');
            const file = fileInput.files[0];
            
            if(file) {
                // نمایش اطلاعات فایل
                document.getElementById('selectedFileInfo').style.display = 'block';
                document.getElementById('fileName').textContent = file.name;
                document.getElementById('fileSize').textContent = 'حجم: ' + formatFileSize(file.size);
                
                // نمایش دکمه آپلود
                document.getElementById('uploadBtn').style.display = 'block';
                
                // بررسی حجم فایل
                const maxSize = 50 * 1024 * 1024; // 50MB
                if(file.size > maxSize) {
                    alert('حجم فایل نباید بیشتر از 50MB باشد');
                    resetFileInput();
                    return;
                }
            }
        }
        
        // تابع آپلود فایل
        function uploadFile(taskId) {
            const fileInput = document.getElementById('fileInput');
            if(!fileInput.files.length) {
                alert('لطفاً فایلی انتخاب کنید');
                return;
            }
            
            const file = fileInput.files[0];
            const maxSize = 50 * 1024 * 1024;
            
            if(file.size > maxSize) {
                alert('حجم فایل نباید بیشتر از 50MB باشد');
                return;
            }
            
            // نمایش progress bar
            const uploadProgress = document.getElementById('uploadProgress');
            const progressBar = document.getElementById('progressBar');
            const uploadBtn = document.getElementById('uploadBtn');
            
            uploadProgress.style.display = 'block';
            progressBar.style.width = '0%';
            uploadBtn.disabled = true;
            uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> در حال آپلود...';
            
            const formData = new FormData();
            formData.append('file', file);
            formData.append('task_id', taskId);
            
            const xhr = new XMLHttpRequest();
            
            xhr.upload.onprogress = function(e) {
                if(e.lengthComputable) {
                    const percent = (e.loaded / e.total) * 100;
                    progressBar.style.width = percent + '%';
                }
            };
            
            xhr.onload = function() {
                if(xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if(response.success) {
                            alert('✅ فایل با موفقیت آپلود شد!');
                            location.reload();
                        } else {
                            alert('❌ خطا: ' + response.error);
                            resetFileInput();
                        }
                    } catch(e) {
                        alert('✅ فایل با موفقیت آپلود شد!');
                        location.reload();
                    }
                } else {
                    alert('❌ خطا در آپلود فایل');
                    resetFileInput();
                }
            };
            
            xhr.onerror = function() {
                alert('❌ خطا در اتصال به سرور');
                resetFileInput();
            };
            
            xhr.open('POST', 'upload.php');
            xhr.send(formData);
        }
        
        // تابع فرمت‌بندی حجم فایل
        function formatFileSize(bytes) {
            if(bytes === 0) return '0 بایت';
            const k = 1024;
            const sizes = ['بایت', 'کیلوبایت', 'مگابایت', 'گیگابایت'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return (bytes / Math.pow(k, i)).toFixed(2) + ' ' + sizes[i];
        }
        
        // ریست فرم آپلود
        function resetFileInput() {
            document.getElementById('fileInput').value = '';
            document.getElementById('selectedFileInfo').style.display = 'none';
            document.getElementById('uploadProgress').style.display = 'none';
            document.getElementById('uploadBtn').style.display = 'none';
            document.getElementById('uploadBtn').disabled = false;
            document.getElementById('uploadBtn').innerHTML = '<i class="fas fa-upload"></i> آپلود فایل';
        }
        
        // تابع کپی به کلیپ‌بورد
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                alert('✅ لینک کپی شد!');
            }).catch(function() {
                // روش قدیمی برای مرورگرهای قدیمی
                const textArea = document.createElement('textarea');
                textArea.value = text;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                alert('✅ لینک کپی شد!');
            });
        }
        
        // توابع تقویم
        function showCalendar() {
            const currentDate = document.getElementById('task_date').value;
            let year = 1403, month = 1, day = 1;
            
            if(currentDate) {
                const parts = currentDate.split('/');
                if(parts.length === 3) {
                    year = parseInt(parts[0]);
                    month = parseInt(parts[1]);
                    day = parseInt(parts[2]);
                }
            }
            
            const monthNames = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
            const today = new Date();
            const todayJalali = gregorianToJalali(today.getFullYear(), today.getMonth() + 1, today.getDate());
            
            let calendarHTML = `
                <div style="text-align: center;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <button onclick="changeCalendarMonth(-1)" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; padding: 5px 10px;">‹</button>
                        <div>
                            <select id="calMonth" style="padding: 8px; border-radius: 5px; border: 1px solid #ddd; font-family: 'Vazirmatn', sans-serif;">
                                ${monthNames.map((name, idx) => 
                                    `<option value="${idx+1}" ${idx+1 == month ? 'selected' : ''}>${name}</option>`
                                ).join('')}
                            </select>
                            <select id="calYear" style="padding: 8px; border-radius: 5px; border: 1px solid #ddd; font-family: 'Vazirmatn', sans-serif;">
                                ${Array.from({length: 11}, (_, i) => 1400 + i).map(y => 
                                    `<option value="${y}" ${y == year ? 'selected' : ''}>${y}</option>`
                                ).join('')}
                            </select>
                        </div>
                        <button onclick="changeCalendarMonth(1)" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; padding: 5px 10px;">›</button>
                    </div>
                    <div style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 5px; margin-bottom: 10px;">
                        <div style="font-weight: bold; padding: 8px;">ش</div>
                        <div style="font-weight: bold; padding: 8px;">ی</div>
                        <div style="font-weight: bold; padding: 8px;">د</div>
                        <div style="font-weight: bold; padding: 8px;">س</div>
                        <div style="font-weight: bold; padding: 8px;">چ</div>
                        <div style="font-weight: bold; padding: 8px;">پ</div>
                        <div style="font-weight: bold; padding: 8px;">ج</div>
                    </div>
                    <div id="calendarDays" style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 5px;"></div>
                    <button onclick="closeCalendar()" style="width: 100%; padding: 10px; margin-top: 15px; background: #dc3545; color: white; border: none; border-radius: 5px; cursor: pointer;">بستن</button>
                </div>
            `;
            
            document.getElementById('calendarPopup').innerHTML = calendarHTML;
            document.getElementById('calendarPopup').style.display = 'block';
            document.getElementById('calendarOverlay').style.display = 'block';
            
            generateCalendarDays(year, month, day);
            
            document.getElementById('calMonth').addEventListener('change', function() {
                generateCalendarDays(
                    parseInt(document.getElementById('calYear').value),
                    parseInt(this.value),
                    day
                );
            });
            
            document.getElementById('calYear').addEventListener('change', function() {
                generateCalendarDays(
                    parseInt(this.value),
                    parseInt(document.getElementById('calMonth').value),
                    day
                );
            });
        }
        
        function generateCalendarDays(year, month, selectedDay) {
            const daysInMonth = getJalaliMonthDays(year, month);
            const startDay = getJalaliStartDay(year, month);
            
            let daysHTML = '';
            
            // خانه‌های خالی قبل از شروع ماه
            for(let i = 0; i < startDay; i++) {
                daysHTML += '<div style="padding: 10px;"></div>';
            }
            
            // روزهای ماه
            for(let i = 1; i <= daysInMonth; i++) {
                const isToday = isTodayJalali(year, month, i);
                const isSelected = (i == selectedDay);
                
                let dayStyle = 'padding: 10px; text-align: center; cursor: pointer; border-radius: 5px;';
                
                if(isToday) {
                    dayStyle += 'background: #2ecc71; color: white;';
                } else if(isSelected) {
                    dayStyle += 'background: #3498db; color: white;';
                } else {
                    dayStyle += 'background: #f8f9fa;';
                }
                
                daysHTML += `<div style="${dayStyle}" onclick="selectCalendarDate(${year}, ${month}, ${i})">${i}</div>`;
            }
            
            document.getElementById('calendarDays').innerHTML = daysHTML;
        }
        
        function selectCalendarDate(year, month, day) {
            document.getElementById('task_date').value = 
                year + '/' + 
                month.toString().padStart(2, '0') + '/' + 
                day.toString().padStart(2, '0');
            closeCalendar();
        }
        
        function changeCalendarMonth(direction) {
            const monthSelect = document.getElementById('calMonth');
            const yearSelect = document.getElementById('calYear');
            
            let month = parseInt(monthSelect.value);
            let year = parseInt(yearSelect.value);
            
            month += direction;
            
            if(month > 12) {
                month = 1;
                year++;
            } else if(month < 1) {
                month = 12;
                year--;
            }
            
            monthSelect.value = month;
            yearSelect.value = year;
            
            generateCalendarDays(year, month, 
                parseInt(document.getElementById('task_date').value.split('/')[2] || 1)
            );
        }
        
        function closeCalendar() {
            document.getElementById('calendarPopup').style.display = 'none';
            document.getElementById('calendarOverlay').style.display = 'none';
        }
        
        // توابع کمکی برای تاریخ شمسی
        function getJalaliMonthDays(year, month) {
            if(month <= 6) return 31;
            if(month <= 11) return 30;
            return isJalaliLeapYear(year) ? 30 : 29;
        }
        
        function isJalaliLeapYear(year) {
            return (((year - 474) % 128) <= 29);
        }
        
        function getJalaliStartDay(year, month) {
            // یک مقدار ثابت برمی‌گرداند (برای سادگی)
            // در نسخه کامل باید محاسبه دقیق انجام شود
            return 0;
        }
        
        function isTodayJalali(year, month, day) {
            const today = new Date();
            const jalali = gregorianToJalali(today.getFullYear(), today.getMonth() + 1, today.getDate());
            return year === jalali.year && month === jalali.month && day === jalali.day;
        }
        
        function gregorianToJalali(gy, gm, gd) {
            const g_d_m = [0,31,59,90,120,151,181,212,243,273,304,334];
            const gy2 = (gm > 2) ? (gy + 1) : gy;
            let days = 355666 + (365 * gy) + Math.floor((gy2 + 3) / 4) - Math.floor((gy2 + 99) / 100) + Math.floor((gy2 + 399) / 400) + gd + g_d_m[gm - 1];
            let jy = -1595 + 33 * Math.floor(days / 12053);
            days %= 12053;
            jy += 4 * Math.floor(days / 1461);
            days %= 1461;
            if (days > 365) {
                jy += Math.floor((days - 1) / 365);
                days = (days - 1) % 365;
            }
            const jm = (days < 186) ? 1 + Math.floor(days / 31) : 7 + Math.floor((days - 186) / 30);
            const jd = 1 + ((days < 186) ? (days % 31) : ((days - 186) % 30));
            return {year: jy, month: jm, day: jd};
        }
    </script>
</body>
</html>