<?php
// فعال کردن نمایش خطاها
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// تنظیمات دیتابیس
define('DB_HOST', 'localhost');
define('DB_USER', 'karapum1_re_ur'); // نام کاربری دیتابیس
define('DB_PASS', '@Mahmood68'); // رمز عبور دیتابیس
define('DB_NAME', 'karapum1_re-db'); // نام دیتابیس

// تنظیمات سایت
define('SITE_URL', 'http://karapump.ir/project-reminder/'); // آدرس سایت شما
define('UPLOAD_PATH', 'uploads/');


// اتصال به دیتابیس
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8mb4'"
        )
    );
} catch(PDOException $e) {
    die("خطا در اتصال به دیتابیس: " . $e->getMessage());
}

// توابع تبدیل تاریخ میلادی به شمسی
function gregorian_to_jalali($gy, $gm, $gd) {
    // بررسی معتبر بودن تاریخ ورودی
    if(!checkdate($gm, $gd, $gy)) {
        return array($gy, $gm, $gd); // در صورت خطا همان تاریخ را برگردان
    }
    
    $g_d_m = array(0,31,59,90,120,151,181,212,243,273,304,334);
    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
    $days = 355666 + (365 * $gy) + ((int)(($gy2 + 3) / 4)) - ((int)(($gy2 + 99) / 100)) + ((int)(($gy2 + 399) / 400)) + $gd + $g_d_m[$gm - 1];
    $jy = -1595 + (33 * ((int)($days / 12053)));
    $days %= 12053;
    $jy += 4 * ((int)($days / 1461));
    $days %= 1461;
    if ($days > 365) {
        $jy += (int)(($days - 1) / 365);
        $days = ($days - 1) % 365;
    }
    $jm = ($days < 186) ? 1 + (int)($days / 31) : 7 + (int)(($days - 186) / 30);
    $jd = 1 + (($days < 186) ? ($days % 31) : (($days - 186) % 30));
    return array($jy, $jm, $jd);
}

// تابع تبدیل تاریخ شمسی به میلادی
function jalali_to_gregorian($jy, $jm, $jd) {
    // بررسی معتبر بودن تاریخ ورودی
    if($jy < 1000 || $jy > 2000 || $jm < 1 || $jm > 12 || $jd < 1 || $jd > 31) {
        // بازگشت تاریخ امروز در صورت خطا
        return array(date('Y'), date('m'), date('d'));
    }
    
    $jy += 1595;
    $days = -355668 + (365 * $jy) + (((int)($jy / 33)) * 8) + ((int)((($jy % 33) + 3) / 4)) + $jd + (($jm < 7) ? ($jm - 1) * 31 : (($jm - 7) * 30) + 186);
    $gy = 400 * ((int)($days / 146097));
    $days %= 146097;
    if ($days > 36524) {
        $gy += 100 * ((int)(--$days / 36524));
        $days %= 36524;
        if ($days >= 365) $days++;
    }
    $gy += 4 * ((int)($days / 1461));
    $days %= 1461;
    if ($days > 365) {
        $gy += (int)(($days - 1) / 365);
        $days = ($days - 1) % 365;
    }
    $gd = $days + 1;
    $sal_a = array(0,31,(($gy % 4 == 0 and $gy % 100 != 0) or ($gy % 400 == 0)) ? 29 : 28,31,30,31,30,31,31,30,31,30,31);
    for ($gm = 0; $gm < 13 and $gd > $sal_a[$gm]; $gm++) $gd -= $sal_a[$gm];
    return array($gy, $gm, $gd);
}

// تابع جدید برای محاسبه روز شروع ماه شمسی (ساده‌تر)
function get_jalali_start_day($year, $month) {
    // محدود کردن مقادیر
    if($year < 1300 || $year > 1500 || $month < 1 || $month > 12) {
        return 0;
    }
    
    // تبدیل به میلادی
    $gregorian = jalali_to_gregorian($year, $month, 1);
    
    // بررسی معتبر بودن تاریخ
    if(!checkdate($gregorian[1], $gregorian[2], $gregorian[0])) {
        return 0;
    }
    
    try {
        $date_str = sprintf("%04d-%02d-%02d", $gregorian[0], $gregorian[1], $gregorian[2]);
        $date = new DateTime($date_str);
        $day_of_week = $date->format('w'); // 0=یکشنبه, 1=دوشنبه, ...
        
        // تبدیل به شنبه=0
        $start_day = ($day_of_week + 1) % 7;
        return $start_day;
    } catch (Exception $e) {
        return 0;
    }
}

// تابع جدید برای بررسی معتبر بودن تاریخ شمسی
function is_valid_jalali_date($year, $month, $day) {
    if ($month < 1 || $month > 12) return false;
    if ($day < 1) return false;
    
    $days_in_month = get_jalali_month_days($year, $month);
    return $day <= $days_in_month;
}

// تابع جدید برای تعداد روزهای ماه شمسی
function get_jalali_month_days($year, $month) {
    if ($month <= 6) return 31;
    if ($month <= 11) return 30;
    
    // اسفند - بررسی سال کبیسه
    return ($year % 4 == 3) ? 30 : 29;
}

// تابع بهبود یافته برای نمایش تاریخ شمسی
function show_jalali_date($date) {
    if(empty($date) || $date == '0000-00-00' || $date == '0000-00-00 00:00:00') return '-';
    
    try {
        $date_parts = explode('-', $date);
        if(count($date_parts) != 3) return '-';
        
        list($year, $month, $day) = $date_parts;
        
        // حذف زمان اگر وجود دارد
        $day = (int) $day;
        
        // بررسی معتبر بودن تاریخ میلادی
        if(!checkdate($month, $day, $year)) return '-';
        
        $jalali = gregorian_to_jalali($year, $month, $day);
        
        // بررسی معتبر بودن تاریخ شمسی
        if(!is_valid_jalali_date($jalali[0], $jalali[1], $jalali[2])) return '-';
        
        $months = array('فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 
                       'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند');
        
        return $jalali[2] . ' ' . $months[$jalali[1]-1] . ' ' . $jalali[0];
    } catch (Exception $e) {
        return '-';
    }
}

// تابع جدید: تبدیل تاریخ شمسی به رشته با فرمت yyyy/mm/dd
function jalali_to_string($year, $month, $day) {
    return sprintf("%04d/%02d/%02d", $year, $month, $day);
}

// تابع جدید: تبدیل رشته تاریخ شمسی به آرایه
function string_to_jalali($date_string) {
    $parts = explode('/', $date_string);
    if(count($parts) != 3) return array(0, 0, 0);
    
    return array(
        (int)$parts[0],
        (int)$parts[1],
        (int)$parts[2]
    );
}

// تابع جدید: تبدیل تاریخ میلادی به رشته با فرمت yyyy-mm-dd
function gregorian_to_string($year, $month, $day) {
    return sprintf("%04d-%02d-%02d", $year, $month, $day);
}

// تابع جدید: دریافت تاریخ امروز به شمسی
function get_today_jalali() {
    $today = date('Y-m-d');
    $parts = explode('-', $today);
    return gregorian_to_jalali($parts[0], $parts[1], $parts[2]);
}

// تابع تولید لینک کوتاه
function generate_short_url() {
    return substr(md5(uniqid()), 0, 8);
}

// تابع جدید: اعتبارسنجی ایمیل
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// تابع جدید: اعتبارسنجی شماره تلفن (ایرانی)
function validate_phone($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    return preg_match('/^09[0-9]{9}$/', $phone);
}

// تابع جدید: پاکسازی متن
function sanitize_text($text) {
    return htmlspecialchars(trim($text), ENT_QUOTES, 'UTF-8');
}

// تابع جدید: فرمت‌بندی عدد
function format_number($number) {
    return number_format($number, 0, '.', ',');
}

// تابع جدید: فرمت‌بندی تاریخ و زمان
function format_datetime($datetime, $format = 'Y/m/d H:i') {
    if(empty($datetime) || $datetime == '0000-00-00 00:00:00') {
        return '-';
    }
    
    try {
        $date = new DateTime($datetime);
        return $date->format($format);
    } catch (Exception $e) {
        return '-';
    }
}

// تابع جدید: بررسی وجود فایل آپلود
function validate_uploaded_file($file) {
    if(!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    
    if(!is_uploaded_file($file['tmp_name'])) {
        return false;
    }
    
    // بررسی نوع فایل
    $allowed_types = array(
        'image/jpeg',
        'image/png',
        'image/gif',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/zip',
        'application/x-rar-compressed',
        'text/plain'
    );
    
    $file_type = mime_content_type($file['tmp_name']);
    if(!in_array($file_type, $allowed_types)) {
        return false;
    }
    
    // بررسی حجم فایل (حداکثر 50MB)
    if($file['size'] > 50 * 1024 * 1024) {
        return false;
    }
    
    return true;
}

// تابع جدید: آپلود فایل
function upload_file($file, $directory = null) {
    if(!validate_uploaded_file($file)) {
        return array('success' => false, 'error' => 'فایل معتبر نیست');
    }
    
    $upload_dir = $directory ?: UPLOAD_PATH;
    
    // ایجاد پوشه اگر وجود ندارد
    if(!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // تولید نام فایل
    $original_name = basename($file['name']);
    $extension = pathinfo($original_name, PATHINFO_EXTENSION);
    $safe_name = preg_replace('/[^a-zA-Z0-9._-]/', '_', pathinfo($original_name, PATHINFO_FILENAME));
    $filename = $safe_name . '_' . time() . '_' . rand(1000, 9999) . '.' . $extension;
    $filepath = $upload_dir . $filename;
    
    // جلوگیری از overwrite
    $counter = 1;
    while(file_exists($filepath)) {
        $filename = $safe_name . '_' . time() . '_' . rand(1000, 9999) . '_' . $counter . '.' . $extension;
        $filepath = $upload_dir . $filename;
        $counter++;
    }
    
    // انتقال فایل
    if(move_uploaded_file($file['tmp_name'], $filepath)) {
        return array(
            'success' => true,
            'filename' => $filename,
            'original_name' => $original_name,
            'filepath' => $filepath,
            'short_url' => generate_short_url()
        );
    } else {
        return array('success' => false, 'error' => 'خطا در انتقال فایل');
    }
}

// تابع جدید: حذف فایل
function delete_file($filename) {
    $filepath = UPLOAD_PATH . $filename;
    if(file_exists($filepath)) {
        return unlink($filepath);
    }
    return false;
}

// تابع جدید: دریافت لیست ماه‌های شمسی
function get_jalali_months() {
    return array(
        'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور',
        'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'
    );
}

// تابع جدید: دریافت لیست سال‌های شمسی
function get_jalali_years($start = 1400, $end = 1410) {
    $years = array();
    for($i = $start; $i <= $end; $i++) {
        $years[] = $i;
    }
    return $years;
}

// تابع جدید: محاسبه اختلاف بین دو تاریخ
function date_diff_jalali($date1, $date2) {
    try {
        $d1 = new DateTime($date1);
        $d2 = new DateTime($date2);
        $interval = $d1->diff($d2);
        
        return array(
            'days' => $interval->days,
            'months' => $interval->m,
            'years' => $interval->y,
            'total_days' => $interval->days
        );
    } catch (Exception $e) {
        return array('days' => 0, 'months' => 0, 'years' => 0, 'total_days' => 0);
    }
}

// تابع جدید: افزودن روز به تاریخ
function add_days_to_date($date, $days) {
    try {
        $datetime = new DateTime($date);
        $datetime->modify("+{$days} days");
        return $datetime->format('Y-m-d');
    } catch (Exception $e) {
        return $date;
    }
}

// تابع جدید: نمایش پیام
function show_message($type, $message) {
    $_SESSION['message'] = array(
        'type' => $type,
        'text' => $message
    );
}

// تابع جدید: دریافت پیام
function get_message() {
    if(isset($_SESSION['message'])) {
        $message = $_SESSION['message'];
        unset($_SESSION['message']);
        return $message;
    }
    return null;
}

// تابع جدید: ریدایرکت
function redirect($url) {
    header("Location: $url");
    exit;
}

// تابع جدید: بررسی آیا کاربر لاگین کرده است
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// تابع جدید: لاگ اوت
function logout() {
    session_destroy();
    redirect('index.php');
}

// تابع جدید: تولید توکن امنیتی
function generate_token() {
    return bin2hex(random_bytes(32));
}

// تابع جدید: بررسی توکن
function verify_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// تابع جدید: محدود کردن دسترسی
function require_login() {
    if(!is_logged_in()) {
        redirect('login.php');
    }
}

// تنظیم توکن CSRF اگر وجود ندارد
if(empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = generate_token();
}

// تنظیم منطقه زمانی
date_default_timezone_set('Asia/Tehran');

// تنظیم زبان
setlocale(LC_ALL, 'fa_IR.UTF-8');

// جلوگیری از Caching برای صفحات پویا
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// تنظیمات اضافی برای امنیت
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // در صورت استفاده از HTTPS روی 1 تنظیم شود

// تعریف ثابت‌های اضافی
define('MAX_FILE_SIZE', 50 * 1024 * 1024); // 50MB
define('ALLOWED_FILE_TYPES', serialize(array(
    'image/jpeg',
    'image/png',
    'image/gif',
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/zip',
    'text/plain'
)));

// توابع کمکی برای دیباگ
function debug($data) {
    echo '<pre>';
    print_r($data);
    echo '</pre>';
}

function debug_die($data) {
    debug($data);
    die();
}

// تابع جدید: فیلتر کردن ورودی‌ها
function filter_input_array_custom($type, $definition) {
    $filtered = array();
    foreach($definition as $key => $options) {
        if(isset($type[$key])) {
            if(is_array($options)) {
                $filtered[$key] = filter_var($type[$key], $options['filter'], $options['options'] ?? array());
            } else {
                $filtered[$key] = filter_var($type[$key], $options);
            }
        }
    }
    return $filtered;
}

// تابع جدید: هش کردن رمز عبور
function hash_password($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// تابع جدید: بررسی رمز عبور
function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

// پایان فایل config.php
?>