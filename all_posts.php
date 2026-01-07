<?php
session_start();
require_once 'config.php';

// 1. التأكد من تسجيل الدخول
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    header("Location: login.php");
    exit;
}

$national_id = $_SESSION['user_id'];
$conn->set_charset("utf8mb4");

// جلب بيانات الطالب للهيدر
$stmtStudent = $conn->prepare("SELECT full_name FROM students WHERE national_id = ? LIMIT 1");
$stmtStudent->bind_param("s", $national_id);
$stmtStudent->execute();
$student = $stmtStudent->get_result()->fetch_assoc();
$stmtStudent->close();

// 2. جلب الإعلانات التي لم يتم مطابقتها للطالب
$sqlAllAds = "
SELECT sp.* FROM social_posts sp
WHERE sp.id NOT IN (
    SELECT post_id 
    FROM chef_post_matches 
    WHERE graduate_id = ?
)
ORDER BY sp.id DESC
";

$stmt = $conn->prepare($sqlAllAds);
$stmt->bind_param("s", $national_id);
$stmt->execute();
$allAds = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>شيف لينك | استكشاف كافة الإعلانات</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Tajawal', sans-serif; background-color: #111827; }
        .glass-card { background: rgba(31, 41, 55, 0.6); backdrop-filter: blur(8px); border: 1px solid rgba(75, 85, 99, 0.4); }
        
        /* تنسيق صورة الشيف في الزاوية اليسرى */
        .bottom-left-chef {
            position: fixed;
            bottom: 0;
            left: 0;
            z-index: 100;
            width: 250px;
            pointer-events: none;
        }
    </style>
</head>
<body class="text-white min-h-screen pb-20 relative">

<div class="bottom-left-chef">
    <img src="images/girl.png" alt="Chef Character" class="w-full h-auto drop-shadow-2xl">
</div>

<header class="bg-gray-900/90 p-4 shadow-2xl border-b border-yellow-500/30 sticky top-0 z-50 backdrop-blur-md">
    <div class="max-w-7xl mx-auto flex justify-between items-center relative">
        
        <a href="student_dashboard.php" class="flex items-center gap-3 bg-gray-800/50 py-2 px-4 rounded-2xl border border-gray-700/50 hover:bg-gray-700/50 hover:border-yellow-500/50 transition-all duration-300 group">
            <div class="h-8 w-8 bg-yellow-500/20 rounded-full flex items-center justify-center text-xl border border-yellow-500/30 group-hover:scale-110 transition-transform">👨‍🍳</div>
            <div class="text-right">
                
                <p class="text-sm font-black text-yellow-500 leading-none group-hover:text-yellow-400"><?= htmlspecialchars($student['full_name']) ?></p>
            </div>
        </a>

        <div class="absolute left-1/2 -translate-x-1/2">
            <h1 class="text-2xl font-black text-yellow-500 tracking-tighter uppercase">CHEF-LINK</h1>
        </div>

        <div class="flex items-center">
            <a href="student_posts.php" class="text-xs bg-gray-800 hover:bg-yellow-500 hover:text-black px-4 py-2 rounded-xl transition-all font-bold flex items-center gap-2 border border-gray-700">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                </svg>
                العوده للمطابقه الذكية
            </a>
        </div>
    </div>
</header>

<main class="max-w-4xl mx-auto p-6 space-y-10 mt-6">

    <div class="border-r-8 border-gray-600 pr-6">
        <h2 class="text-3xl font-black text-white">استكشاف الإعلانات العامة</h2>
        <p class="text-gray-400 mt-2">نعرض لك هنا بقية الإعلانات المتوفرة في النظام والتي قد تهمك بعيداً عن مهاراتك المسجلة.</p>
    </div>

    <div class="grid gap-6">
        <?php if (empty($allAds)): ?>
            <div class="text-center py-20 bg-gray-900/20 rounded-3xl border-2 border-dashed border-gray-800 italic text-gray-500">
                لا توجد إعلانات إضافية حالياً..
            </div>
        <?php else: ?>
            <?php foreach ($allAds as $ad): ?>
                <div class="glass-card p-6 rounded-[1.5rem] group transition-all duration-300 shadow-lg border-r-4 border-gray-500 hover:border-yellow-500/40">
                    
                    <div class="flex justify-between items-center mb-4">
                        <span class="bg-gray-800 text-yellow-500 text-[10px] font-bold px-3 py-1 rounded-full border border-gray-700 uppercase tracking-widest">
                            <?= htmlspecialchars($ad['platform']) ?>
                        </span>
                        <span class="text-[10px] text-gray-500 italic uppercase">General Listing</span>
                    </div>

                    <div class="text-gray-400 text-sm leading-relaxed mb-8 pr-4 group-hover:text-gray-200 transition-colors">
                        <?= nl2br(htmlspecialchars($ad['content'])) ?>
                    </div>

                    <a href="<?= htmlspecialchars($ad['post_url']) ?>" target="_blank" 
                       class="w-full bg-gray-800 hover:bg-yellow-500 text-white hover:text-black font-bold py-4 rounded-xl flex items-center justify-center gap-3 transition-all transform active:scale-95 group/btn">
                        <span>الانتقال لمصدر الإعلان</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 transition-transform group-hover/btn:translate-x-[-5px]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                    </a>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</main>

</body>
</html>