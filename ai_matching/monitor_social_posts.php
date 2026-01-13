<?php
/**
 * ai_matching/monitor_social_posts.php
 */

// إعدادات قاعدة البيانات مباشرة داخل الملف
$db_config = [
    'host' => '127.0.0.1',
    'user' => 'root',
    'password' => '',
    'database' => 'chef-link'
];

// الاتصال بقاعدة البيانات
$conn = new mysqli($db_config['host'], $db_config['user'], $db_config['password'], $db_config['database']);

if ($conn->connect_error) {
    die("❌ فشل الاتصال: " . $conn->connect_error);
}

// 1. فحص قاعدة البيانات: هل يوجد منشورات لم تُعالج؟ (is_processed = 0)
$check_sql = "SELECT id FROM social_posts WHERE is_processed = 0 LIMIT 1";
$result = $conn->query($check_sql);

echo "--- [" . date('Y-m-d H:i:s') . "] فحص قاعدة البيانات... ---\n";

if ($result && $result->num_rows > 0) {
    echo "🚨 تم العثور على منشورات جديدة! جاري تشغيل محرك الماتشنغ...\n";

  $pythonPath = 'C:\Users\electro1\AppData\Local\Programs\Python\Python314\python.exe'; 

// تأكد من وضع حرف r قبل المسار في بايثون أو استخدام الـ Backslashes الصحيحة في PHP
$scriptPath = __DIR__ . DIRECTORY_SEPARATOR . "matching.py";

$command = "\"$pythonPath\" \"$scriptPath\"";

    // تشغيل سكريبت البايثون والتقاط المخرجات
    $output = shell_exec($command . " 2>&1");

    if ($output) {
        echo "--------------------------\n";
        echo "مخرجات سكريبت البايثون:\n";
        echo $output;
        echo "--------------------------\n";
    }
} else {
    echo "😴 لا توجد منشورات جديدة حالياً.\n";
}

$conn->close();
?>