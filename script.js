$(document).ready(function() {
    // Drag & Drop ساده
    $('.task-card').on('mousedown', function(e) {
        $(this).addClass('dragging');
        $('body').append('<div class="drag-ghost"></div>');
        $('.drag-ghost').html($(this).html());
    });
    
    $(document).on('mousemove', function(e) {
        if($('.dragging').length) {
            $('.drag-ghost').css({
                left: e.pageX + 10,
                top: e.pageY + 10,
                position: 'absolute',
                opacity: 0.8
            }).show();
        }
    });
    
    $(document).on('mouseup', function(e) {
        $('.task-card').removeClass('dragging');
        $('.drag-ghost').remove();
        
        // اگر روی کانتینر دیگری رها شده باشد
        const overContainer = $(e.target).closest('.tasks-container');
        if(overContainer.length && !$('.dragging').closest('.tasks-container').is(overContainer)) {
            alert('کار منتقل شد! صفحه رفرش می‌شود.');
            setTimeout(function() {
                location.reload();
            }, 500);
        }
    });
    
    // مدیریت چک‌لیست
    $('.checklist-item input[type="checkbox"]').change(function() {
        const isChecked = $(this).is(':checked');
        const checklistId = $(this).closest('.checklist-item').find('input[name="checklist_id[]"]').val();
        
        if(checklistId && checklistId != '0') {
            // ذخیره وضعیت در local storage به عنوان نمونه
            localStorage.setItem('checklist_' + checklistId, isChecked);
        }
    });
    
    // بازگردانی وضعیت چک‌لیست‌ها از local storage
    $('.checklist-item input[name="checklist_id[]"]').each(function() {
        const checklistId = $(this).val();
        if(checklistId && checklistId != '0') {
            const savedState = localStorage.getItem('checklist_' + checklistId);
            if(savedState !== null) {
                $(this).closest('.checklist-item').find('input[type="checkbox"]').prop('checked', savedState === 'true');
            }
        }
    });
    
    // جستجوی کارها
    $('#searchTasks').on('keyup', function() {
        const searchText = $(this).val().toLowerCase();
        $('.task-card').each(function() {
            const title = $(this).find('h4').text().toLowerCase();
            const desc = $(this).find('.task-desc').text().toLowerCase();
            
            if(title.includes(searchText) || desc.includes(searchText)) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });
    
    // فیلتر بر اساس پروژه
    $('#filterProject').change(function() {
        const projectId = $(this).val();
        
        if(projectId === 'all') {
            $('.task-card').show();
        } else {
            $('.task-card').each(function() {
                if($(this).find('.project-badge').text().includes(projectId)) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        }
    });
    
    // مرتب‌سازی کارها
    $('#sortTasks').change(function() {
        const sortBy = $(this).val();
        const container = $('#allTasks');
        const items = container.find('.task-card').toArray();
        
        items.sort(function(a, b) {
            if(sortBy === 'priority') {
                const aPriority = $(a).find('.task-priority').hasClass('priority-3') ? 3 : 
                                 $(a).find('.task-priority').hasClass('priority-2') ? 2 : 1;
                const bPriority = $(b).find('.task-priority').hasClass('priority-3') ? 3 : 
                                 $(b).find('.task-priority').hasClass('priority-2') ? 2 : 1;
                return bPriority - aPriority;
            } else if(sortBy === 'date') {
                const aDate = $(a).find('.task-meta span:first-child').text();
                const bDate = $(b).find('.task-meta span:first-child').text();
                return aDate.localeCompare(bDate);
            }
            return 0;
        });
        
        container.empty();
        items.forEach(item => container.append(item));
    });
    
    // نمایش/مخفی کردن توضیحات
    $('.task-card').on('click', '.toggle-desc', function(e) {
        e.stopPropagation();
        $(this).closest('.task-card').find('.full-desc').toggle();
        $(this).text($(this).text() === 'نمایش بیشتر' ? 'نمایش کمتر' : 'نمایش بیشتر');
    });
    
    // تقویم ساده
    $('#showCalendarBtn').click(function() {
        $('#simpleCalendar').toggle();
    });
    
    // انتخاب تاریخ در تقویم
    $('.calendar-day:not(.empty)').click(function() {
        const day = $(this).text();
        const month = $('#currentMonth').data('month');
        const year = $('#currentMonth').data('year');
        
        if(day && month && year) {
            $('#task_date').val(year + '/' + month + '/' + day);
            $('#simpleCalendar').hide();
        }
    });
    
    // پیش‌نمایش تصویر قبل از آپلود
    $('#fileUpload').change(function() {
        const file = this.files[0];
        if(file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                $('#filePreview').html('<img src="' + e.target.result + '" style="max-width: 200px; max-height: 200px; border-radius: 10px; margin-top: 10px;">');
            }
            reader.readAsDataURL(file);
        }
    });
    
    // نمایش هشدار برای کارهای مهم
    setInterval(function() {
        $('.priority-3').each(function() {
            if(!$(this).hasClass('notified')) {
                $(this).addClass('notified');
                $(this).closest('.task-card').addClass('important-pulse');
                
                // هشدار صوتی (اگر کاربر اجازه داده باشد)
                try {
                    const audio = new Audio('data:audio/wav;base64,UklGRigAAABXQVZFZm10IBIAAAABAAEARKwAAIhYAQACABAAZGF0YQQAAAAAAA==');
                    audio.volume = 0.1;
                    audio.play();
                } catch(e) {}
            }
        });
    }, 30000); // هر 30 ثانیه بررسی کند
    
    // حذف هشدار
    $('.task-card').click(function() {
        $(this).removeClass('important-pulse');
    });
    
    // نمایش وضعیت ذخیره خودکار
    let autoSaveTimer;
    $('input, textarea, select').on('input change', function() {
        clearTimeout(autoSaveTimer);
        $('#autoSaveStatus').text('در حال ذخیره...').addClass('saving');
        
        autoSaveTimer = setTimeout(function() {
            $('#autoSaveStatus').text('ذخیره شد').removeClass('saving').addClass('saved');
            
            setTimeout(function() {
                $('#autoSaveStatus').text('').removeClass('saved');
            }, 2000);
        }, 1000);
    });
    
    // کوکی برای ذخیره تنظیمات کاربر
    $('#darkModeToggle').click(function() {
        $('body').toggleClass('dark-mode');
        localStorage.setItem('darkMode', $('body').hasClass('dark-mode'));
    });
    
    // بارگذاری تنظیمات از local storage
    if(localStorage.getItem('darkMode') === 'true') {
        $('body').addClass('dark-mode');
    }
});

// تابع آپلود فایل
function uploadFile(taskId) {
    const fileInput = $('#fileUpload')[0];
    if(!fileInput.files.length) {
        alert('لطفاً فایلی انتخاب کنید');
        return;
    }
    
    const formData = new FormData();
    formData.append('file', fileInput.files[0]);
    formData.append('task_id', taskId);
    
    $.ajax({
        url: 'upload.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            alert('فایل با موفقیت آپلود شد');
            location.reload();
        },
        error: function() {
            alert('خطا در آپلود فایل');
        }
    });
}

// تابع تولید تقویم ساده
function generateSimpleCalendar(year, month) {
    const monthNames = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
    const daysInMonth = month < 7 ? 31 : (month < 12 ? 30 : 29);
    
    let calendarHTML = '<div class="calendar-popup">';
    calendarHTML += '<div class="calendar-header">';
    calendarHTML += '<button onclick="changeMonth(-1)">‹</button>';
    calendarHTML += '<span>' + monthNames[month-1] + ' ' + year + '</span>';
    calendarHTML += '<button onclick="changeMonth(1)">›</button>';
    calendarHTML += '</div>';
    calendarHTML += '<div class="calendar-days">';
    
    for(let i = 1; i <= daysInMonth; i++) {
        calendarHTML += '<div class="calendar-day" onclick="selectDate(' + year + ',' + month + ',' + i + ')">' + i + '</div>';
    }
    
    calendarHTML += '</div></div>';
    
    return calendarHTML;
}

// توابع کمکی برای تقویم
let currentCalendarYear = 1403;
let currentCalendarMonth = 1;

function showCalendar() {
    $('#calendarContainer').html(generateSimpleCalendar(currentCalendarYear, currentCalendarMonth));
    $('#calendarContainer').show();
}

function changeMonth(direction) {
    currentCalendarMonth += direction;
    if(currentCalendarMonth > 12) {
        currentCalendarMonth = 1;
        currentCalendarYear++;
    } else if(currentCalendarMonth < 1) {
        currentCalendarMonth = 12;
        currentCalendarYear--;
    }
    showCalendar();
}

function selectDate(year, month, day) {
    $('#task_date').val(year + '/' + month + '/' + day);
    $('#calendarContainer').hide();
}

// CSS برای حالت تاریک
const darkModeCSS = `
<style id="darkModeStyles">
.dark-mode {
    background: #1a1a1a;
    color: #f0f0f0;
}

.dark-mode .container {
    background: #2d2d2d;
}

.dark-mode .task-card,
.dark-mode .project-card,
.dark-mode section {
    background: #3d3d3d;
    color: #f0f0f0;
}

.dark-mode .btn {
    background: #404040;
}

.dark-mode .btn:hover {
    background: #505050;
}

.dark-mode .form-control {
    background: #404040;
    color: #f0f0f0;
    border-color: #555;
}

.dark-mode header {
    background: linear-gradient(to left, #2c3e50, #34495e);
}

.important-pulse {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { box-shadow: 0 0 0 0 rgba(231, 76, 60, 0.7); }
    70% { box-shadow: 0 0 0 10px rgba(231, 76, 60, 0); }
    100% { box-shadow: 0 0 0 0 rgba(231, 76, 60, 0); }
}
</style>
`;

$('head').append(darkModeCSS);