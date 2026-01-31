<?php
require_once 'config.php';

// دریافت پروژه‌ها
$projects = $pdo->query("SELECT * FROM projects ORDER BY name")->fetchAll();
$project_id = $_GET['project_id'] ?? '';

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
            INSERT INTO tasks (project_id, title, description, task_date, task_time, progress, priority) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$project_id, $title, $description, $gregorian_date, $task_time, $progress, $priority]);
        $task_id = $pdo->lastInsertId();
        
        // ایجاد چک‌لیست
        if(isset($_POST['checklist_items'])) {
            foreach($_POST['checklist_items'] as $item) {
                if(!empty(trim($item))) {
                    $check_stmt = $pdo->prepare("INSERT INTO checklists (task_id, item) VALUES (?, ?)");
                    $check_stmt->execute([$task_id, trim($item)]);
                }
            }
        }
        
        // آپلود فایل‌ها اگر وجود داشته باشد
        if(isset($_FILES['files']) && !empty($_FILES['files']['name'][0])) {
            $files = $_FILES['files'];
            
            for($i = 0; $i < count($files['name']); $i++) {
                if($files['error'][$i] === UPLOAD_ERR_OK) {
                    $file_name = basename($files['name'][$i]);
                    $file_tmp = $files['tmp_name'][$i];
                    $file_size = $files['size'][$i];
                    
                    // بررسی حجم (حداکثر 50MB)
                    if($file_size <= 50 * 1024 * 1024) {
                        // ایجاد پوشه آپلود اگر وجود ندارد
                        if (!file_exists(UPLOAD_PATH)) {
                            mkdir(UPLOAD_PATH, 0755, true);
                        }
                        
                        // تولید نام جدید
                        $extension = pathinfo($file_name, PATHINFO_EXTENSION);
                        $safe_name = preg_replace('/[^a-zA-Z0-9._-]/', '_', pathinfo($file_name, PATHINFO_FILENAME));
                        $new_filename = $safe_name . '_' . time() . '_' . $i . '.' . $extension;
                        $upload_path = UPLOAD_PATH . $new_filename;
                        
                        if(move_uploaded_file($file_tmp, $upload_path)) {
                            $short_url = generate_short_url();
                            $file_stmt = $pdo->prepare("INSERT INTO files (task_id, filename, original_name, short_url) VALUES (?, ?, ?, ?)");
                            $file_stmt->execute([$task_id, $new_filename, $file_name, $short_url]);
                        }
                    }
                }
            }
        }
        
        header('Location: index.php?msg=task_added');
        exit;
    } catch(PDOException $e) {
        $error = "خطا در ایجاد کار: " . $e->getMessage();
    }
}

// تاریخ امروز به شمسی
$today = gregorian_to_jalali(date('Y'), date('m'), date('d'));
$today_shamsi = $today[0] . '/' . str_pad($today[1], 2, '0', STR_PAD_LEFT) . '/' . str_pad($today[2], 2, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ایجاد کار جدید</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        /* استایل بخش آپلود */
        .upload-section {
            border: 3px dashed #3498db;
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            background: #f8f9fa;
            margin: 20px 0;
            transition: all 0.3s;
        }
        
        .upload-section:hover {
            border-color: #2ecc71;
            background: #e8f5e9;
        }
        
        .upload-section i {
            font-size: 48px;
            color: #3498db;
            margin-bottom: 15px;
        }
        
        .file-list {
            margin-top: 15px;
        }
        
        .file-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            background: white;
            border-radius: 8px;
            margin-bottom: 8px;
            border: 1px solid #e0e0e0;
        }
        
        .file-item i {
            font-size: 20px;
            color: #3498db;
        }
        
        .remove-file {
            background: #e74c3c;
            color: white;
            border: none;
            border-radius: 5px;
            padding: 5px 10px;
            cursor: pointer;
            margin-right: auto;
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
            <h1><i class="fas fa-plus-circle"></i> ایجاد کار جدید</h1>
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
            
            <?php if(isset($_GET['msg']) && $_GET['msg'] == 'task_added'): ?>
            <div class="alert alert-success">
                ✅ کار با موفقیت ایجاد شد!
            </div>
            <?php endif; ?>
            
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="form-row">
                    <div class="form-group">
                        <label for="project_id"><i class="fas fa-project-diagram"></i> پروژه مربوطه</label>
                        <select id="project_id" name="project_id" class="form-control" required>
                            <option value="">انتخاب پروژه</option>
                            <?php foreach($projects as $project): ?>
                            <option value="<?php echo $project['id']; ?>" <?php echo $project['id'] == $project_id ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($project['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="title"><i class="fas fa-heading"></i> عنوان کار</label>
                        <input type="text" id="title" name="title" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="task_date"><i class="fas fa-calendar-alt"></i> تاریخ (شمسی)</label>
                        <div style="display: flex; gap: 10px;">
                            <input type="text" id="task_date" name="task_date" class="form-control" value="<?php echo $today_shamsi; ?>" readonly>
                            <button type="button" onclick="showCalendar()" class="btn btn-info" style="white-space: nowrap;">
                                <i class="fas fa-calendar"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="task_time"><i class="fas fa-clock"></i> ساعت</label>
                        <input type="time" id="task_time" name="task_time" class="form-control" value="08:00">
                    </div>
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
                    <label for="description"><i class="fas fa-file-alt"></i> شرح کار</label>
                    <textarea id="description" name="description" class="form-control" rows="5" placeholder="شرح کامل کار را اینجا بنویسید..."></textarea>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-check-square"></i> چک لیست</label>
                    <div id="checklistContainer">
                        <div class="checklist-item">
                            <input type="checkbox">
                            <input type="text" name="checklist_items[]" placeholder="عنوان آیتم چک لیست...">
                            <button type="button" class="btn-small btn-danger remove-checklist">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    <button type="button" id="addChecklist" class="btn-small btn-success">
                        <i class="fas fa-plus"></i> افزودن آیتم
                    </button>
                </div>
                
                <!-- بخش آپلود فایل -->
                <div class="form-group">
                    <label><i class="fas fa-cloud-upload-alt"></i> آپلود فایل‌ها (اختیاری)</label>
                    <div class="upload-section" onclick="document.getElementById('fileInput').click()">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <h3>فایل‌ها را اینجا رها کنید یا کلیک کنید</h3>
                        <p>حداکثر حجم هر فایل: 50MB</p>
                        <p>می‌توانید چند فایل را همزمان انتخاب کنید</p>
                        <input type="file" id="fileInput" name="files[]" multiple style="display: none;" onchange="handleFiles(this.files)">
                    </div>
                    
                    <div id="fileList" class="file-list"></div>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-success btn-block">
                        <i class="fas fa-check"></i> ایجاد کار و آپلود فایل‌ها
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
                        <input type="checkbox">
                        <input type="text" name="checklist_items[]" placeholder="عنوان آیتم چک لیست...">
                        <button type="button" class="btn-small btn-danger remove-checklist">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                `;
                $('#checklistContainer').append(newItem);
            });
            
            $(document).on('click', '.remove-checklist', function() {
                if($('#checklistContainer .checklist-item').length > 1) {
                    $(this).closest('.checklist-item').remove();
                }
            });
            
            // Drag & Drop برای آپلود
            $('.upload-section').on('dragover', function(e) {
                e.preventDefault();
                $(this).css({
                    'border-color': '#2ecc71',
                    'background': '#e8f5e9'
                });
            });
            
            $('.upload-section').on('dragleave', function() {
                $(this).css({
                    'border-color': '#3498db',
                    'background': '#f8f9fa'
                });
            });
            
            $('.upload-section').on('drop', function(e) {
                e.preventDefault();
                $(this).css({
                    'border-color': '#3498db',
                    'background': '#f8f9fa'
                });
                
                const files = e.originalEvent.dataTransfer.files;
                if(files.length) {
                    document.getElementById('fileInput').files = files;
                    handleFiles(files);
                }
            });
        });
        
        // مدیریت فایل‌های انتخاب شده
        let selectedFiles = [];
        
        function handleFiles(files) {
            const fileList = document.getElementById('fileList');
            fileList.innerHTML = '';
            
            selectedFiles = Array.from(files);
            
            selectedFiles.forEach((file, index) => {
                const fileItem = document.createElement('div');
                fileItem.className = 'file-item';
                fileItem.innerHTML = `
                    <i class="fas fa-file"></i>
                    <span>${file.name}</span>
                    <span style="color: #666; font-size: 0.9rem;">(${formatFileSize(file.size)})</span>
                    <button type="button" class="remove-file" onclick="removeFile(${index})">
                        <i class="fas fa-times"></i>
                    </button>
                `;
                fileList.appendChild(fileItem);
            });
        }
        
        function removeFile(index) {
            selectedFiles.splice(index, 1);
            
            // به‌روزرسانی input file
            const dataTransfer = new DataTransfer();
            selectedFiles.forEach(file => dataTransfer.items.add(file));
            document.getElementById('fileInput').files = dataTransfer.files;
            
            // به‌روزرسانی لیست نمایش
            handleFiles(selectedFiles);
        }
        
        function formatFileSize(bytes) {
            if(bytes === 0) return '0 بایت';
            const k = 1024;
            const sizes = ['بایت', 'کیلوبایت', 'مگابایت', 'گیگابایت'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return (bytes / Math.pow(k, i)).toFixed(2) + ' ' + sizes[i];
        }
        
        // توابع تقویم (همانند edit_task.php)
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
            
            let daysHTML = '';
            
            // روزهای ماه
            for(let i = 1; i <= daysInMonth; i++) {
                const isSelected = (i == selectedDay);
                
                let dayStyle = 'padding: 10px; text-align: center; cursor: pointer; border-radius: 5px;';
                
                if(isSelected) {
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
        
        function getJalaliMonthDays(year, month) {
            if(month <= 6) return 31;
            if(month <= 11) return 30;
            return 29; // اسفند (برای سادگی همیشه 29)
        }
    </script>
</body>
</html>