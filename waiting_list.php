<?php
session_start();
require_once 'config.php';

// التحقق من الجلسة
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    header("Location: login.php");
    exit;
}

$national_id = $_SESSION['user_id'];
$conn->set_charset("utf8mb4");

// جلب بيانات الطالب للهيدر
$student_query = $conn->prepare("SELECT full_name FROM students WHERE national_id = ?");
$student_query->bind_param("s", $national_id);
$student_query->execute();
$student = $student_query->get_result()->fetch_assoc();


// جلب المنشورات التي قيمتها (is_interested = 1) فقط
$sqlInterested = "SELECT sp.id AS post_id, sp.content, sp.post_url, sp.platform, cpm.similarity_score, cpm.matched_at
FROM chef_post_matches cpm
JOIN social_posts sp ON sp.id = cpm.post_id
WHERE cpm.graduate_id = ? 
AND cpm.is_interested = 1
ORDER BY cpm.matched_at DESC";

$stmt = $conn->prepare($sqlInterested);
$stmt->bind_param("s", $national_id);
$stmt->execute();
$ads = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>شيف لينك | المنشورات المهتم بها</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700;900&display=swap" rel="stylesheet">
    <style>
        body { 
            font-family: 'Tajawal', sans-serif; 
            background-color: #111827; 
            display: flex; 
            flex-direction: column; 
            min-height: screen; 
        }
        .glass-card { background: rgba(31, 41, 55, 0.6); backdrop-filter: blur(8px); border: 1px solid rgba(16, 185, 129, 0.2); }
        .bottom-left-chef { position: fixed; bottom: 20px; left: 0; z-index: 50; width: 250px; pointer-events: none;waiting_list.php }
        main { flex: 1 0 auto; }
    </style>
</head>
<body class="text-white">

    <header class="bg-gray-900/90 p-4 shadow-2xl border-b border-yellow-500/30 sticky top-0 z-50 backdrop-blur-md">
        <div class="max-w-7xl mx-auto flex justify-between items-center relative">
            <a href="student_dashboard.php" class="flex items-center gap-3 bg-gray-800/50 py-2 px-4 rounded-2xl border border-gray-700/50">
                <div class="h-8 w-8 bg-yellow-500/20 rounded-full flex items-center justify-center text-xl">👨‍🍳</div>
                <p class="text-sm font-black text-yellow-500"><?= htmlspecialchars($student['full_name'] ?? 'طالب') ?></p>
            </a>
            <h1 class="text-xl md:text-2xl font-black text-yellow-500 tracking-widest uppercase absolute left-1/2 -translate-x-1/2">CHEF-LINK</h1>
            <a href="student_posts.php" class="bg-gray-800 hover:bg-gray-700 text-white px-5 py-2 rounded-2xl text-xs font-bold transition">
                ← العودة للمطابقات
            </a>
        </div>
    </header>

    <div class="bottom-left-chef hidden md:block">
        <img src="images/time.png" alt="Chef Character" class="w-full h-auto drop-shadow-2xl">
    </div>

    <main class="max-w-4xl w-full mx-auto p-6">
        <div class="flex flex-col md:flex-row justify-between items-center mb-10 bg-gray-900/50 p-6 rounded-3xl border border-gray-800 gap-4">
            <div class="text-center md:text-right">
                <h1 class="text-2xl font-black text-white italic">المنشورات المهتم بها</h1>
                <p class="text-gray-400 text-sm">هذه القائمة تحتوي على جميع الوظائف التي أبدت اهتمامك بها وتنتظر تواصل المنشآت معك.</p>
            </div>
            <div class="bg-green-500/10 text-green-400 px-4 py-2 rounded-xl border border-green-500/20 font-bold text-sm">
                عدد العناصر: <?= count($ads) ?>
            </div>
        </div>
        
        <?php if (empty($ads)): ?>
            <div class="text-center py-24 bg-gray-900/20 rounded-3xl border-2 border-dashed border-gray-800 text-gray-500">
                لم تقم بإضافة أي منشور لقائمة الاهتمام بعد..
            </div>
        <?php else: ?>
            <div class="grid gap-6">
                <?php foreach ($ads as $ad): ?>
                    <div class="glass-card p-6 rounded-[2rem] border-r-4 border-green-500 shadow-xl transition hover:translate-x-1">
                        <div class="flex justify-between items-start mb-4">
                            <span class="text-[10px] bg-green-500/20 text-green-400 px-3 py-1 rounded-full font-bold uppercase tracking-wider">
                                مهتم بها ✅
                            </span>
                            <span class="text-xs text-gray-500 font-mono"><?= date('Y-m-d', strtotime($ad['matched_at'])) ?></span>
                        </div>

                        <div class="text-gray-200 text-md leading-relaxed mb-6 italic">
                            "<?= nl2br(htmlspecialchars($ad['content'])) ?>"
                        </div>

                        <div class="flex gap-3">
                            <a href="<?= htmlspecialchars($ad['post_url']) ?>" target="_blank" 
                               class="flex-1 bg-green-600 hover:bg-green-500 text-white text-center py-3 rounded-xl font-bold transition text-sm shadow-lg shadow-green-900/20">
                                عرض الإعلان الأصلي
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

 
</body>
</html>