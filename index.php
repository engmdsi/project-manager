<?php
require_once 'config.php';

// بررسی نصب
$check = $pdo->query("SHOW TABLES LIKE 'projects'");
if($check->rowCount() == 0) {
    header('Location: install.php');
    exit;
}

// پارامترهای ماه تقویم (با مقدار پیش‌فرض امن)
$today_jalali = gregorian_to_jalali(date('Y'), date('m'), date('d'));
$jalali_year = isset($_GET['cal_year']) ? (int)$_GET['cal_year'] : $today_jalali[0];
$jalali_month = isset($_GET['cal_month']) ? (int)$_GET['cal_month'] : $today_jalali[1];

// محدود کردن سال و ماه به مقادیر معقول
if($jalali_year < 1300 || $jalali_year > 1500) $jalali_year = $today_jalali[0];
if($jalali_month < 1 || $jalali_month > 12) $jalali_month = $today_jalali[1];

// محاسبه ماه قبل و بعد با بررسی مرزها
$prev_month = $jalali_month - 1;
$prev_year = $jalali_year;
if ($prev_month < 1) {
    $prev_month = 12;
    $prev_year--;
    if($prev_year < 1300) $prev_year = $today_jalali[0]; // محدود کردن سال
}

$next_month = $jalali_month + 1;
$next_year = $jalali_year;
if ($next_month > 12) {
    $next_month = 1;
    $next_year++;
    if($next_year > 1500) $next_year = $today_jalali[0]; // محدود کردن سال
}

// دریافت پروژه‌ها
$projects = $pdo->query("SELECT * FROM projects ORDER BY priority DESC, created_at DESC")->fetchAll();

// اگر در حال مشاهده کارهای تکمیل شده هستیم
$show_completed = isset($_GET['show_completed']);
$where_condition = $show_completed ? "t.completed = 1" : "t.completed = 0";

// دریافت کارها
$tasks = $pdo->query("
    SELECT t.*, p.name as project_name 
    FROM tasks t 
    LEFT JOIN projects p ON t.project_id = p.id 
    WHERE $where_condition
    ORDER BY t.priority DESC, t.task_date ASC, t.task_time ASC
")->fetchAll();

// تاریخ امروز
$today = date('Y-m-d');

// دریافت کارهای هر تاریخ برای تقویم (فقط کارهای فعال) - با تاریخ معتبر
try {
    $month_start = jalali_to_gregorian($jalali_year, $jalali_month, 1);
    $month_end = jalali_to_gregorian($jalali_year, $jalali_month, 
        get_jalali_month_days($jalali_year, $jalali_month));
    
    // بررسی معتبر بودن تاریخ‌ها
    if(checkdate($month_start[1], $month_start[2], $month_start[0]) && 
       checkdate($month_end[1], $month_end[2], $month_end[0])) {
        
        $month_start_str = sprintf("%04d-%02d-%02d", $month_start[0], $month_start[1], $month_start[2]);
        $month_end_str = sprintf("%04d-%02d-%02d", $month_end[0], $month_end[1], $month_end[2]);
        
        $tasks_by_date_stmt = $pdo->prepare("
            SELECT task_date, COUNT(*) as count 
            FROM tasks 
            WHERE task_date BETWEEN ? AND ? AND completed = 0
            GROUP BY task_date
        ");
        $tasks_by_date_stmt->execute([$month_start_str, $month_end_str]);
        $tasks_by_date = $tasks_by_date_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    } else {
        $tasks_by_date = [];
    }
} catch (Exception $e) {
    $tasks_by_date = [];
}

// اگر پروژه خاصی انتخاب شده
$selected_project_id = $_GET['project_id'] ?? 0;
$selected_date = $_GET['date'] ?? '';

// فیلتر کارها بر اساس پروژه یا تاریخ انتخاب شده
$filtered_tasks = $tasks;
if ($selected_project_id > 0) {
    $filtered_tasks = array_filter($tasks, function($task) use ($selected_project_id) {
        return $task['project_id'] == $selected_project_id;
    });
} elseif ($selected_date) {
    $filtered_tasks = array_filter($tasks, function($task) use ($selected_date) {
        return $task['task_date'] == $selected_date;
    });
}

// کارهای امروز (فقط کارهای فعال)
$today_tasks = array_filter($tasks, function($task) use ($today) {
    return $task['task_date'] == $today;
});

// کارهای تکمیل شده
$completed_tasks_count = $pdo->query("SELECT COUNT(*) FROM tasks WHERE completed = 1")->fetchColumn();

// دریافت چک‌لیست‌ها برای هر کار
$checklists_by_task = [];
foreach($tasks as $task) {
    $stmt = $pdo->prepare("SELECT item, is_checked FROM checklists WHERE task_id = ? ORDER BY id LIMIT 3");
    $stmt->execute([$task['id']]);
    $checklists_by_task[$task['id']] = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>سیستم مدیریت پروژه</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1><i class="fas fa-tasks"></i> مدیریت پروژه</h1>
            <div class="header-actions">
                <a href="add_project.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> <span class="desktop-only">پروژه جدید</span>
                </a>
                <a href="add_task.php" class="btn btn-success">
                    <i class="fas fa-plus-circle"></i> <span class="desktop-only">کار جدید</span>
                </a>
                <?php if($show_completed): ?>
                    <a href="index.php" class="btn btn-info">
                        <i class="fas fa-arrow-right"></i> <span class="desktop-only">کارهای فعال</span>
                    </a>
                <?php else: ?>
                    <a href="index.php?show_completed=1" class="btn btn-warning">
                        <i class="fas fa-check-circle"></i> <span class="desktop-only">کارهای تکمیل شده</span>
                        <span class="mobile-only">(<?php echo $completed_tasks_count; ?>)</span>
                    </a>
                <?php endif; ?>
            </div>
        </header>

        <div class="dashboard">
            <?php if(!$show_completed): ?>
            <!-- تقویم کوچک با قابلیت ناوبری -->
            <section class="mini-calendar">
                <div class="calendar-header">
                    <button onclick="changeCalendarMonth(-1)" class="calendar-nav-btn">
                        <i class="fas fa-chevron-right"></i> ماه قبل
                    </button>
                    <h2>
                        <i class="fas fa-calendar"></i> 
                        <?php 
                        $month_names = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 
                                       'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
                        echo $jalali_year . ' ' . $month_names[$jalali_month-1];
                        ?>
                        <?php if($selected_date): ?>
                        <span style="font-size: 0.9rem; color: #666;">
                            (انتخاب شده: <?php echo show_jalali_date($selected_date); ?>)
                        </span>
                        <?php endif; ?>
                    </h2>
                    <button onclick="changeCalendarMonth(1)" class="calendar-nav-btn">
                        ماه بعد <i class="fas fa-chevron-left"></i>
                    </button>
                </div>
                
                <div class="calendar-days-small">
                    <div class="calendar-day-small day-header-small">ش</div>
                    <div class="calendar-day-small day-header-small">ی</div>
                    <div class="calendar-day-small day-header-small">د</div>
                    <div class="calendar-day-small day-header-small">س</div>
                    <div class="calendar-day-small day-header-small">چ</div>
                    <div class="calendar-day-small day-header-small">پ</div>
                    <div class="calendar-day-small day-header-small">ج</div>
                    
                    <?php
                    // محاسبه روز شروع ماه
                    $start_day = get_jalali_start_day($jalali_year, $jalali_month);
                    
                    // تعداد روزهای ماه
                    $month_days = get_jalali_month_days($jalali_year, $jalali_month);
                    
                    // خانه‌های خالی
                    for($i = 0; $i < $start_day; $i++):
                        echo '<div class="calendar-day-small empty"></div>';
                    endfor;
                    
                    // روزهای ماه
                    for($day = 1; $day <= $month_days; $day++):
                        // تبدیل به میلادی با بررسی خطا
                        try {
                            $gregorian_day = jalali_to_gregorian($jalali_year, $jalali_month, $day);
                            
                            // بررسی معتبر بودن تاریخ
                            if(checkdate($gregorian_day[1], $gregorian_day[2], $gregorian_day[0])) {
                                $gregorian_date = sprintf("%04d-%02d-%02d", $gregorian_day[0], $gregorian_day[1], $gregorian_day[2]);
                            } else {
                                $gregorian_date = '';
                            }
                        } catch (Exception $e) {
                            $gregorian_date = '';
                        }
                        
                        $is_today = ($day == $today_jalali[2] && $jalali_month == $today_jalali[1] && $jalali_year == $today_jalali[0]) ? ' today' : '';
                        $is_selected = ($gregorian_date == $selected_date) ? ' selected' : '';
                        $has_tasks = (!empty($gregorian_date) && isset($tasks_by_date[$gregorian_date]) && $tasks_by_date[$gregorian_date] > 0) ? ' has-tasks' : '';
                        $task_count = (!empty($gregorian_date) && isset($tasks_by_date[$gregorian_date])) ? $tasks_by_date[$gregorian_date] : 0;
                        
                        $onclick = !empty($gregorian_date) ? "onclick='selectDate(\"{$gregorian_date}\")'" : "";
                        
                        echo "<div class='calendar-day-small{$is_today}{$is_selected}{$has_tasks}' 
                                {$onclick}
                                title='{$day} {$month_names[$jalali_month-1]}'>";
                        
                        if($task_count > 0) {
                            echo "<span class='task-count-badge'>{$task_count}</span>";
                        }
                        
                        echo $day;
                        echo "</div>";
                    endfor;
                    ?>
                </div>
                
                <?php if($selected_date): ?>
                <a href="index.php?cal_year=<?php echo $jalali_year; ?>&cal_month=<?php echo $jalali_month; ?>" class="clear-filters">
                    <i class="fas fa-times"></i> حذف فیلتر تاریخ
                </a>
                <?php endif; ?>
            </section>

            <!-- پروژه‌ها -->
            <section class="projects-summary">
                <h2>
                    <i class="fas fa-project-diagram"></i> پروژه‌ها
                    <?php if($selected_project_id > 0): ?>
                    <span style="font-size: 0.9rem; color: #666;">
                        (فیلتر شده)
                    </span>
                    <?php endif; ?>
                </h2>
                <div class="projects-grid">
                    <?php foreach($projects as $project): 
                        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tasks WHERE project_id = ? AND completed = 0");
                        $stmt->execute([$project['id']]);
                        $result = $stmt->fetch();
                        $task_count = $result ? $result['count'] : 0;
                        
                        $is_selected = ($selected_project_id == $project['id']) ? ' selected' : '';
                    ?>
                    <div class="project-card<?php echo $is_selected; ?>" data-id="<?php echo $project['id']; ?>">
                        <div class="project-header">
                            <h3><?php echo htmlspecialchars($project['name']); ?></h3>
                            <span class="priority priority-<?php echo $project['priority']; ?>">
                                <?php echo $project['priority'] == 1 ? 'عادی' : ($project['priority'] == 2 ? 'متوسط' : 'فوری'); ?>
                            </span>
                        </div>
                        <div class="project-stats">
                            <span><i class="fas fa-tasks"></i> <?php echo $task_count; ?> کار</span>
                            <span><i class="fas fa-chart-line"></i> <?php echo $project['progress']; ?>%</span>
                        </div>
                        <div class="progress-container">
                            <div class="progress-bar" style="width: <?php echo $project['progress']; ?>%">
                                <span class="progress-text"><?php echo $project['progress']; ?>%</span>
                            </div>
                        </div>
                        <div class="project-actions">
                            <a href="edit_project.php?id=<?php echo $project['id']; ?>" class="btn-small btn-edit" title="ویرایش">
                                <i class="fas fa-edit"></i>
                            </a>
                            <button type="button" onclick="filterByProject(<?php echo $project['id']; ?>)" 
                                    class="btn-small btn-info" title="نمایش کارهای این پروژه">
                                <i class="fas fa-eye"></i>
                            </button>
                            <a href="add_task.php?project_id=<?php echo $project['id']; ?>" 
                               class="btn-small btn-add" title="کار جدید">
                                <i class="fas fa-plus"></i>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if($selected_project_id > 0): ?>
                <a href="index.php?cal_year=<?php echo $jalali_year; ?>&cal_month=<?php echo $jalali_month; ?>" class="clear-filters">
                    <i class="fas fa-times"></i> حذف فیلتر پروژه
                </a>
                <?php endif; ?>
            </section>

            <!-- کارهای امروز -->
            <section class="today-tasks">
                <h2>
                    <i class="fas fa-calendar-day"></i> کارهای امروز
                    <span class="tasks-count"><?php echo count($today_tasks); ?></span>
                </h2>
                <?php if(count($today_tasks) > 0): ?>
                <div class="tasks-container" id="todayTasks">
                    <?php foreach($today_tasks as $task): 
                        $checklists = $checklists_by_task[$task['id']] ?? [];
                    ?>
                    <div class="task-card" data-id="<?php echo $task['id']; ?>">
                        <div class="task-header">
                            <h4><?php echo htmlspecialchars($task['title']); ?></h4>
                            <span class="task-priority priority-<?php echo $task['priority']; ?>"></span>
                        </div>
                        <div class="task-meta">
                            <span><i class="far fa-clock"></i> <?php echo substr($task['task_time'], 0, 5); ?></span>
                            <span class="project-badge"><?php echo htmlspecialchars($task['project_name']); ?></span>
                        </div>
                        <p class="task-desc"><?php echo nl2br(htmlspecialchars(substr($task['description'], 0, 60))); ?></p>
                        
                        <!-- نمایش چک‌لیست -->
                        <?php if(count($checklists) > 0): ?>
                        <div class="task-checklist">
                            <?php 
                            $checklist_count = 0;
                            foreach($checklists as $checklist):
                                if($checklist_count < 3):
                            ?>
                            <div class="checklist-item-small <?php echo $checklist['is_checked'] ? 'checked' : ''; ?>">
                                <input type="checkbox" <?php echo $checklist['is_checked'] ? 'checked' : ''; ?> disabled>
                                <span><?php echo htmlspecialchars($checklist['item']); ?></span>
                            </div>
                            <?php 
                                    $checklist_count++;
                                endif;
                            endforeach; 
                            
                            // اگر بیشتر از 3 آیتم وجود دارد
                            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM checklists WHERE task_id = ?");
                            $stmt->execute([$task['id']]);
                            $total_checklists = $stmt->fetch()['count'];
                            
                            if($total_checklists > 3):
                            ?>
                            <div class="checklist-more" onclick="showTaskDetails(<?php echo $task['id']; ?>)">
                                <i class="fas fa-ellipsis-h"></i> <?php echo $total_checklists - 3; ?> آیتم دیگر
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="task-actions">
                            <div class="task-action-buttons">
                                <button onclick="completeTask(<?php echo $task['id']; ?>)" class="btn-tiny btn-success" title="تکمیل کار">
                                    <i class="fas fa-check"></i>
                                </button>
                                <a href="edit_task.php?id=<?php echo $task['id']; ?>" class="btn-tiny btn-info" title="ویرایش">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button onclick="deleteTask(<?php echo $task['id']; ?>)" class="btn-tiny btn-danger" title="حذف">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                            <div class="task-progress"><?php echo $task['progress']; ?>%</div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="no-tasks">هیچ کاری برای امروز تعریف نشده است.</p>
                <?php endif; ?>
            </section>
            <?php endif; ?>

            <!-- همه کارها (فیلتر شده) -->
            <section class="all-tasks">
                <div class="completed-tasks-header">
                    <h2>
                        <i class="fas fa-list-check"></i> 
                        <?php if($show_completed): ?>
                        <i class="fas fa-check-circle"></i> کارهای تکمیل شده
                        <?php elseif($selected_project_id > 0): ?>
                        کارهای پروژه انتخاب شده
                        <?php elseif($selected_date): ?>
                        کارهای تاریخ انتخاب شده
                        <?php else: ?>
                        همه کارها
                        <?php endif; ?>
                        <span class="tasks-count"><?php echo count($filtered_tasks); ?></span>
                    </h2>
                    
                    <?php if($show_completed): ?>
                    <div class="task-action-buttons">
                        <button onclick="uncompleteAllTasks()" class="btn-small btn-warning" title="بازگردانی همه">
                            <i class="fas fa-undo"></i> <span class="desktop-only">بازگردانی همه</span>
                        </button>
                        <button onclick="deleteAllCompleted()" class="btn-small btn-danger" title="حذف همه">
                            <i class="fas fa-trash"></i> <span class="desktop-only">حذف همه</span>
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if(count($filtered_tasks) > 0): ?>
                <div class="tasks-container" id="allTasks">
                    <?php foreach($filtered_tasks as $task): 
                        $is_completed = $task['completed'] == 1;
                        $checklists = $checklists_by_task[$task['id']] ?? [];
                    ?>
                    <div class="task-card <?php echo $is_completed ? 'completed' : ''; ?>" data-id="<?php echo $task['id']; ?>">
                        <div class="task-header">
                            <h4><?php echo htmlspecialchars($task['title']); ?></h4>
                            <span class="task-priority priority-<?php echo $task['priority']; ?>"></span>
                        </div>
                        <div class="task-meta">
                            <span><i class="far fa-calendar"></i> <?php echo show_jalali_date($task['task_date']); ?></span>
                            <span><i class="far fa-clock"></i> <?php echo substr($task['task_time'], 0, 5); ?></span>
                            <span class="project-badge"><?php echo htmlspecialchars($task['project_name']); ?></span>
                        </div>
                        <p class="task-desc"><?php echo nl2br(htmlspecialchars(substr($task['description'], 0, 80))); ?></p>
                        
                        <!-- نمایش چک‌لیست -->
                        <?php if(count($checklists) > 0): ?>
                        <div class="task-checklist">
                            <?php 
                            $checklist_count = 0;
                            foreach($checklists as $checklist):
                                if($checklist_count < 3):
                            ?>
                            <div class="checklist-item-small <?php echo $checklist['is_checked'] ? 'checked' : ''; ?>">
                                <input type="checkbox" <?php echo $checklist['is_checked'] ? 'checked' : ''; ?> disabled>
                                <span><?php echo htmlspecialchars($checklist['item']); ?></span>
                            </div>
                            <?php 
                                    $checklist_count++;
                                endif;
                            endforeach; 
                            
                            // اگر بیشتر از 3 آیتم وجود دارد
                            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM checklists WHERE task_id = ?");
                            $stmt->execute([$task['id']]);
                            $total_checklists = $stmt->fetch()['count'];
                            
                            if($total_checklists > 3):
                            ?>
                            <div class="checklist-more" onclick="showTaskDetails(<?php echo $task['id']; ?>)">
                                <i class="fas fa-ellipsis-h"></i> <?php echo $total_checklists - 3; ?> آیتم دیگر
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="task-actions">
                            <div class="task-action-buttons">
                                <?php if($is_completed): ?>
                                <button onclick="uncompleteTask(<?php echo $task['id']; ?>)" class="btn-tiny btn-warning" title="بازگردانی">
                                    <i class="fas fa-undo"></i>
                                </button>
                                <a href="edit_task.php?id=<?php echo $task['id']; ?>" class="btn-tiny btn-info" title="ویرایش">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button onclick="deleteTask(<?php echo $task['id']; ?>)" class="btn-tiny btn-danger" title="حذف">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <?php else: ?>
                                <button onclick="completeTask(<?php echo $task['id']; ?>)" class="btn-tiny btn-success" title="تکمیل کار">
                                    <i class="fas fa-check"></i>
                                </button>
                                <a href="edit_task.php?id=<?php echo $task['id']; ?>" class="btn-tiny btn-info" title="ویرایش">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button onclick="deleteTask(<?php echo $task['id']; ?>)" class="btn-tiny btn-danger" title="حذف">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                            <div class="task-progress"><?php echo $task['progress']; ?>%</div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="no-tasks">
                    <?php if($show_completed): ?>
                    هیچ کار تکمیل شده‌ای وجود ندارد.
                    <?php elseif($selected_project_id > 0): ?>
                    هیچ کاری برای این پروژه وجود ندارد.
                    <?php elseif($selected_date): ?>
                    هیچ کاری برای این تاریخ وجود ندارد.
                    <?php else: ?>
                    هنوز هیچ کاری ایجاد نشده است.
                    <?php endif; ?>
                </p>
                <?php endif; ?>
            </section>
        </div>
    </div>

    <!-- مودال برای نمایش جزئیات کار -->
    <div id="taskModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div style="background: white; padding: 20px; border-radius: 10px; max-width: 500px; width: 90%; max-height: 80vh; overflow-y: auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h3 id="modalTitle"></h3>
                <button onclick="closeTaskModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer;">&times;</button>
            </div>
            <div id="modalContent"></div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // تغییر ماه در تقویم
        function changeCalendarMonth(direction) {
            const currentUrl = new URL(window.location.href);
            const params = new URLSearchParams(currentUrl.search);
            
            let year = <?php echo $jalali_year; ?>;
            let month = <?php echo $jalali_month; ?>;
            
            month += direction;
            
            if(month > 12) {
                month = 1;
                year++;
            } else if(month < 1) {
                month = 12;
                year--;
            }
            
            params.set('cal_year', year);
            params.set('cal_month', month);
            
            // حذف تاریخ انتخاب شده هنگام تغییر ماه
            params.delete('date');
            
            window.location.href = 'index.php?' + params.toString();
        }
        
        // انتخاب تاریخ در تقویم
        function selectDate(date) {
            if(!date) return; // اگر تاریخ معتبر نباشد
            
            const currentUrl = new URL(window.location.href);
            const params = new URLSearchParams(currentUrl.search);
            
            params.set('date', date);
            params.set('cal_year', <?php echo $jalali_year; ?>);
            params.set('cal_month', <?php echo $jalali_month; ?>);
            
            window.location.href = 'index.php?' + params.toString();
        }
        
        // فیلتر بر اساس پروژه
        function filterByProject(projectId) {
            const currentUrl = new URL(window.location.href);
            const params = new URLSearchParams(currentUrl.search);
            
            params.set('project_id', projectId);
            params.set('cal_year', <?php echo $jalali_year; ?>);
            params.set('cal_month', <?php echo $jalali_month; ?>);
            
            // حذف تاریخ انتخاب شده هنگام فیلتر پروژه
            params.delete('date');
            
            window.location.href = 'index.php?' + params.toString();
        }
        
        // نمایش جزئیات کار با چک‌لیست کامل
        function showTaskDetails(taskId) {
            $.ajax({
                url: 'get_task_details.php',
                method: 'GET',
                data: { task_id: taskId },
                success: function(response) {
                    if(response.success) {
                        $('#modalTitle').text(response.task.title);
                        $('#modalContent').html(response.html);
                        $('#taskModal').show();
                    }
                },
                error: function() {
                    alert('خطا در دریافت اطلاعات کار');
                }
            });
        }
        
        function closeTaskModal() {
            $('#taskModal').hide();
        }
        
        // تکمیل کار
        function completeTask(taskId) {
            if(confirm('آیا از تکمیل این کار اطمینان دارید؟')) {
                $.ajax({
                    url: 'complete_task.php',
                    method: 'POST',
                    data: { task_id: taskId, action: 'complete' },
                    success: function(response) {
                        if(response.success) {
                            location.reload();
                        } else {
                            alert('خطا در تکمیل کار');
                        }
                    },
                    error: function() {
                        alert('خطا در ارتباط با سرور');
                    }
                });
            }
        }
        
        // بازگردانی کار تکمیل شده
        function uncompleteTask(taskId) {
            if(confirm('آیا از بازگردانی این کار اطمینان دارید؟')) {
                $.ajax({
                    url: 'complete_task.php',
                    method: 'POST',
                    data: { task_id: taskId, action: 'uncomplete' },
                    success: function(response) {
                        if(response.success) {
                            location.reload();
                        } else {
                            alert('خطا در بازگردانی کار');
                        }
                    },
                    error: function() {
                        alert('خطا در ارتباط با سرور');
                    }
                });
            }
        }
        
        // بازگردانی همه کارهای تکمیل شده
        function uncompleteAllTasks() {
            if(confirm('آیا از بازگردانی همه کارهای تکمیل شده اطمینان دارید؟')) {
                $.ajax({
                    url: 'complete_task.php',
                    method: 'POST',
                    data: { action: 'uncomplete_all' },
                    success: function(response) {
                        if(response.success) {
                            location.reload();
                        } else {
                            alert('خطا در بازگردانی کارها');
                        }
                    },
                    error: function() {
                        alert('خطا در ارتباط با سرور');
                    }
                });
            }
        }
        
        // حذف کار
        function deleteTask(taskId) {
            if(confirm('آیا از حذف این کار اطمینان دارید؟\nاین عمل قابل بازگشت نیست.')) {
                $.ajax({
                    url: 'delete_task.php',
                    method: 'POST',
                    data: { task_id: taskId },
                    success: function(response) {
                        if(response.success) {
                            location.reload();
                        } else {
                            alert('خطا در حذف کار');
                        }
                    },
                    error: function() {
                        alert('خطا در ارتباط با سرور');
                    }
                });
            }
        }
        
        // حذف همه کارهای تکمیل شده
        function deleteAllCompleted() {
            if(confirm('آیا از حذف همه کارهای تکمیل شده اطمینان دارید؟\nاین عمل قابل بازگشت نیست.')) {
                $.ajax({
                    url: 'delete_task.php',
                    method: 'POST',
                    data: { action: 'delete_all_completed' },
                    success: function(response) {
                        if(response.success) {
                            location.reload();
                        } else {
                            alert('خطا در حذف کارها');
                        }
                    },
                    error: function() {
                        alert('خطا در ارتباط با سرور');
                    }
                });
            }
        }
        
        // بستن مودال با کلیک خارج از آن
        $(document).on('click', function(e) {
            if($(e.target).is('#taskModal')) {
                closeTaskModal();
            }
        });
        
        // بستن مودال با کلید ESC
        $(document).on('keydown', function(e) {
            if(e.key === 'Escape' && $('#taskModal').is(':visible')) {
                closeTaskModal();
            }
        });
    </script>
    
    <style>
        /* مخفی کردن متن در موبایل */
        .mobile-only {
            display: none;
        }
        
        @media (max-width: 768px) {
            .desktop-only {
                display: none;
            }
            
            .mobile-only {
                display: inline;
            }
            
            .btn span {
                display: none;
            }
            
            .btn i {
                margin: 0;
            }
        }
    </style>
</body>
</html>