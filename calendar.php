<?php
require_once 'config.php';

// تاریخ فعلی
$current_year = date('Y');
$current_month = date('m');
$current_day = date('d');

// تبدیل به شمسی
$current_jalali = gregorian_to_jalali($current_year, $current_month, $current_day);
$jalali_year = $current_jalali[0];
$jalali_month = $current_jalali[1];
$jalali_day = $current_jalali[2];

// نام ماه‌های شمسی
$jalali_months = [
    'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور',
    'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'
];

// روزهای هفته
$week_days = ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'];

// دریافت کارهای ماه جاری
$first_day_gregorian = jalali_to_gregorian($jalali_year, $jalali_month, 1);
$last_day_gregorian = jalali_to_gregorian($jalali_year, $jalali_month, 31);

$first_day_str = $first_day_gregorian[0] . '-' . str_pad($first_day_gregorian[1], 2, '0', STR_PAD_LEFT) . '-01';
$last_day_str = $last_day_gregorian[0] . '-' . str_pad($last_day_gregorian[1], 2, '0', STR_PAD_LEFT) . '-31';

$tasks_stmt = $pdo->prepare("
    SELECT task_date, COUNT(*) as count 
    FROM tasks 
    WHERE task_date BETWEEN ? AND ?
    GROUP BY task_date
");
$tasks_stmt->execute([$first_day_str, $last_day_str]);
$tasks_by_date = $tasks_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// محاسبه روز شروع ماه
$first_day_jalali = jalali_to_gregorian($jalali_year, $jalali_month, 1);
$first_day_week = date('w', mktime(0, 0, 0, $first_day_jalali[1], $first_day_jalali[2], $first_day_jalali[0]));
$first_day_week = ($first_day_week + 1) % 7; // تطبیق با شروع هفته از شنبه

// تعداد روزهای ماه
$month_days = 31;
if ($jalali_month >= 1 && $jalali_month <= 6) {
    $month_days = 31;
} elseif ($jalali_month >= 7 && $jalali_month <= 11) {
    $month_days = 30;
} else {
    // اسفند - بررسی سال کبیسه
    $month_days = ($jalali_year % 4 == 3) ? 30 : 29;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تقویم شمسی</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1><i class="fas fa-calendar-alt"></i> تقویم شمسی</h1>
            <div class="header-actions">
                <a href="index.php" class="btn btn-info"><i class="fas fa-home"></i> صفحه اصلی</a>
            </div>
        </header>
        
        <div class="calendar-container">
            <div class="calendar-header">
                <h2><?php echo $jalali_months[$jalali_month - 1] . ' ' . $jalali_year; ?></h2>
                <div class="calendar-nav">
                    <a href="?year=<?php echo $jalali_year - 1; ?>&month=<?php echo $jalali_month; ?>" class="btn-small">
                        <i class="fas fa-chevron-right"></i> سال قبل
                    </a>
                    <a href="?year=<?php echo $jalali_year; ?>&month=<?php echo $jalali_month - 1; ?>" class="btn-small">
                        <i class="fas fa-chevron-right"></i> ماه قبل
                    </a>
                    <a href="calendar.php" class="btn-small">امروز</a>
                    <a href="?year=<?php echo $jalali_year; ?>&month=<?php echo $jalali_month + 1; ?>" class="btn-small">
                        ماه بعد <i class="fas fa-chevron-left"></i>
                    </a>
                    <a href="?year=<?php echo $jalali_year + 1; ?>&month=<?php echo $jalali_month; ?>" class="btn-small">
                        سال بعد <i class="fas fa-chevron-left"></i>
                    </a>
                </div>
            </div>
            
            <div class="calendar-grid">
                <!-- روزهای هفته -->
                <?php foreach($week_days as $day): ?>
                <div class="calendar-day day-header"><?php echo $day; ?></div>
                <?php endforeach; ?>
                
                <!-- خانه‌های خالی قبل از شروع ماه -->
                <?php for($i = 0; $i < $first_day_week; $i++): ?>
                <div class="calendar-day empty"></div>
                <?php endfor; ?>
                
                <!-- روزهای ماه -->
                <?php for($day = 1; $day <= $month_days; $day++): 
                    $gregorian_date = jalali_to_gregorian($jalali_year, $jalali_month, $day);
                    $gregorian_str = $gregorian_date[0] . '-' . str_pad($gregorian_date[1], 2, '0', STR_PAD_LEFT) . '-' . str_pad($gregorian_date[2], 2, '0', STR_PAD_LEFT);
                    $has_task = isset($tasks_by_date[$gregorian_str]) ? $tasks_by_date[$gregorian_str] : 0;
                    $is_today = ($day == $jalali_day) ? 'today' : '';
                    $has_task_class = $has_task > 0 ? 'has-task' : '';
                ?>
                <div class="calendar-day <?php echo $is_today . ' ' . $has_task_class; ?>" 
                     data-date="<?php echo $gregorian_str; ?>"
                     title="<?php echo $has_task > 0 ? $has_task . ' کار' : 'هیچ کاری'; ?>">
                    <?php echo $day; ?>
                    <?php if($has_task > 0): ?>
                    <span class="task-count"><?php echo $has_task; ?></span>
                    <?php endif; ?>
                </div>
                <?php endfor; ?>
            </div>
            
            <!-- کارهای تاریخ انتخاب شده -->
            <div id="dayTasks" style="margin-top: 30px; display: none;">
                <h3>کارهای تاریخ انتخاب شده</h3>
                <div id="tasksList"></div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            $('.calendar-day:not(.empty):not(.day-header)').click(function() {
                const selectedDate = $(this).data('date');
                
                // حذف انتخاب قبلی
                $('.calendar-day').removeClass('selected');
                $(this).addClass('selected');
                
                if (selectedDate) {
                    // دریافت کارهای این تاریخ
                    $.ajax({
                        url: 'get_day_tasks.php',
                        method: 'POST',
                        data: { date: selectedDate },
                        success: function(response) {
                            if (response.tasks.length > 0) {
                                let tasksHTML = '';
                                response.tasks.forEach(function(task) {
                                    tasksHTML += `
                                        <div class="task-card">
                                            <h4>${task.title}</h4>
                                            <p>${task.description.substring(0, 100)}...</p>
                                            <a href="edit_task.php?id=${task.id}" class="btn-small">مشاهده</a>
                                        </div>
                                    `;
                                });
                                $('#tasksList').html(tasksHTML);
                                $('#dayTasks').show();
                            } else {
                                $('#tasksList').html('<p>هیچ کاری برای این تاریخ وجود ندارد.</p>');
                                $('#dayTasks').show();
                            }
                        }
                    });
                }
            });
        });
    </script>
    
    <style>
    .calendar-day {
        position: relative;
        padding: 15px;
        text-align: center;
        border-radius: 10px;
        cursor: pointer;
        transition: all 0.3s ease;
        background: #f8f9fa;
        font-weight: 600;
        min-height: 80px;
    }
    
    .calendar-day:hover {
        background: #4facfe;
        color: white;
    }
    
    .calendar-day.today {
        background: #2ecc71;
        color: white;
        border: 2px solid #27ae60;
    }
    
    .calendar-day.has-task {
        background: #3498db;
        color: white;
    }
    
    .calendar-day.selected {
        background: #e74c3c;
        color: white;
        border: 2px solid #c0392b;
    }
    
    .calendar-day.day-header {
        background: #2c3e50;
        color: white;
        font-weight: bold;
        cursor: default;
    }
    
    .calendar-day.empty {
        background: transparent;
        cursor: default;
    }
    
    .calendar-day.empty:hover {
        background: transparent;
    }
    
    .task-count {
        position: absolute;
        top: 5px;
        left: 5px;
        background: #e74c3c;
        color: white;
        border-radius: 50%;
        width: 20px;
        height: 20px;
        font-size: 0.8rem;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .calendar-nav {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    </style>
</body>
</html>