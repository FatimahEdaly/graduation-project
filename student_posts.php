<?php
session_start();
require_once 'config.php';

// --- الجزء الأول: منطق التفاعل ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $student_id = $_SESSION['user_id'];
    $post_id = intval($_POST['post_id']);
    
    if ($_POST['action'] === 'interested') {
        $stmt = $conn->prepare("UPDATE chef_post_matches SET is_interested = 1 WHERE graduate_id = ? AND post_id = ?");
    } elseif ($_POST['action'] === 'not_interested') {
        $stmt = $conn->prepare("UPDATE chef_post_matches SET is_interested = 0 WHERE graduate_id = ? AND post_id = ?");
    }
    
    $stmt->bind_param("si", $student_id, $post_id);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error']);
    }
    $stmt->close();
    exit;
}

// --- الجزء الثاني: جلب بيانات العرض ---
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    header("Location: login.php");
    exit;
}

$national_id = $_SESSION['user_id'];
$conn->set_charset("utf8mb4");

$stmtStudent = $conn->prepare("SELECT full_name, skills FROM students WHERE national_id = ? LIMIT 1");
$stmtStudent->bind_param("s", $national_id);
$stmtStudent->execute();
$student = $stmtStudent->get_result()->fetch_assoc();
$stmtStudent->close();

$sqlAds = "SELECT sp.id AS post_id, sp.content, sp.post_url, sp.platform, cpm.similarity_score, cpm.matched_at
FROM chef_post_matches cpm
JOIN social_posts sp ON sp.id = cpm.post_id
WHERE cpm.graduate_id = ? 
AND cpm.is_interested IS NULL
ORDER BY cpm.similarity_score DESC, cpm.matched_at DESC";

$stmtAds = $conn->prepare($sqlAds);
$stmtAds->bind_param("s", $national_id);
$stmtAds->execute();
$ads = $stmtAds->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtAds->close();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>شيف لينك | لوحة التحكم الذكية</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Tajawal', sans-serif; background-color: #111827; }
        .score-bar { height: 10px; border-radius: 10px; background-color: #374151; overflow: hidden; }
        .score-fill { height: 100%; background: linear-gradient(90deg, #f59e0b, #fbbf24); transition: width 1.5s ease-out; }
        .pulse-badge { animation: pulse-animation 2s infinite; }
        @keyframes pulse-animation { 
            0% { box-shadow: 0 0 0 0px rgba(245, 158, 11, 0.6); }
            100% { box-shadow: 0 0 0 12px rgba(245, 158, 11, 0); }
        }
        .glass-card { background: rgba(31, 41, 55, 0.6); backdrop-filter: blur(8px); border: 1px solid rgba(75, 85, 99, 0.4); transition: all 0.4s ease; }
        .bottom-left-chef { position: fixed; bottom: 0; left: 0; z-index: 100; width: 250px; pointer-events: none; }
        
        /* أزرار الهوفر */
        .btn-base { background-color: rgba(55, 65, 81, 0.3); border: 1px solid rgba(75, 85, 99, 0.4); color: #9ca3af; transition: all 0.3s ease; }
        .btn-interested:hover { background-color: #10b981; color: white; border-color: #10b981; }
        .btn-not-interested:hover { background-color: #ef4444; color: white; border-color: #ef4444; }
        .btn-details:hover { background-color: #f59e0b; color: #000; border-color: #f59e0b; }
        
    </style>
</head>
<body class="text-white min-h-screen pb-24 relative">

<div class="bottom-left-chef">
    <img src="images/girl.png" alt="Chef Character" class="w-full h-auto drop-shadow-2xl">
</div>

<div class="fixed top-24 right-6 z-50 hidden md:block">
    <a href="all_posts.php" class="flex items-center gap-3 bg-gray-900/90 backdrop-blur-md border-r-4 border-yellow-500 text-white px-6 py-4 rounded-xl shadow-2xl hover:bg-yellow-500 hover:text-black transition-all duration-300 group">
        <span class="text-base font-bold">استكشاف كافة الإعلانات</span>
    </a>
</div>

<header class="bg-gray-900/90 p-4 shadow-2xl border-b border-yellow-500/30 sticky top-0 z-50 backdrop-blur-md">
    <div class="max-w-7xl mx-auto flex justify-between items-center relative">
        <a href="student_dashboard.php" class="flex items-center gap-3 bg-gray-800/50 py-2 px-4 rounded-2xl border border-gray-700/50">
            <div class="h-8 w-8 bg-yellow-500/20 rounded-full flex items-center justify-center text-xl">👨‍🍳</div>
            <p class="text-sm font-black text-yellow-500"><?= htmlspecialchars($student['full_name']) ?></p>
        </a>

        <h1 class="text-xl md:text-2xl font-black text-yellow-500 tracking-widest uppercase absolute left-1/2 -translate-x-1/2">CHEF-LINK</h1>

        <a href="waiting_list.php" class="bg-gray-800 hover:bg-green-600 text-green-500 hover:text-white border border-green-500/30 px-4 py-2 rounded-xl transition-all font-bold text-xs">
            <span>⭐ المهتم بها</span>
        </a>
    </div>
</header>

<main class="max-w-4xl mx-auto p-6 space-y-8 mt-4">
    
    <div class="bg-gray-900 p-8 rounded-[2rem] border-r-[10px] border-yellow-500 shadow-2xl flex flex-col gap-8 relative overflow-hidden">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 relative z-10">
            <div>
                <h2 class="text-3xl font-black text-white mb-2 italic">فرصك المختارة بدقة</h2>
                <p class="text-gray-400 text-base italic leading-relaxed">بناءً على نظام المطابقة الذكي الخاص بشيف لينك.</p>
            </div>
            <div class="pulse-badge bg-yellow-500 text-black px-10 py-4 rounded-2xl font-black text-2xl shadow-lg flex flex-col items-center">
                <span><?= count($ads) ?></span>
                <span class="text-xs uppercase font-bold tracking-tighter">Matched</span>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 relative z-10">
            <div class="bg-gray-800/50 p-5 rounded-2xl border border-gray-700/50 group hover:border-yellow-500/30 transition-all">
                <h4 class="text-yellow-500 text-xs font-black mb-2 uppercase tracking-widest">مهاراتك الحالية:</h4>
                <p class="text-gray-200 text-lg italic font-medium leading-relaxed">"<?= htmlspecialchars($student['skills']) ?>"</p>
            </div>

            <div class="bg-blue-900/10 p-5 rounded-2xl border border-blue-500/20">
                <div class="flex items-start gap-3 text-blue-400">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mt-1" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                    </svg>
                    <div>
                        <h4 class="text-xs font-black mb-1 uppercase tracking-widest text-blue-300">نصيحة تقنية للمطابقة:</h4>
                        <p class="text-gray-300 text-sm leading-relaxed">يرجى التأكد من كتابة مهاراتك بدقة مع <span class="text-blue-300 font-bold underline">وضع فاصلة (،) بين كل مهارة</span>.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="matches-container" class="grid gap-8">
        <?php if (empty($ads)): ?>
            <div class="text-center py-24 bg-gray-900/20 rounded-3xl border-2 border-dashed border-gray-800 text-gray-400 text-lg italic">لا توجد وظائف جديدة مطابقة لمهاراتك حالياً..</div>
        <?php else: ?>
            <?php foreach ($ads as $ad): ?>
                <div class="glass-card p-8 rounded-[2.5rem] group relative" id="card-<?= $ad['post_id'] ?>">
                    <div class="flex justify-between items-start mb-6">
                        <div class="w-full max-w-[250px]">
                            <div class="flex justify-between text-xs mb-2 font-black text-yellow-500 uppercase tracking-tighter">
                                <span>قوة المطابقة</span>
                                <span><?= number_format($ad['similarity_score'] * 100, 0) ?>%</span>
                            </div>
                            <div class="score-bar"><div class="score-fill" style="width: <?= $ad['similarity_score'] * 100 ?>%"></div></div>
                        </div>
                        <span class="text-xs text-yellow-500/60 uppercase font-black tracking-widest"><?= htmlspecialchars($ad['platform']) ?></span>
                    </div>

                    <div class="text-gray-200 text-lg leading-relaxed mb-8 pr-6 border-r-4 border-gray-700">
                        <?= nl2br(htmlspecialchars($ad['content'])) ?>
                    </div>

                    <div class="flex flex-col sm:flex-row items-center gap-4">
                        <button onclick="handleResponse(<?= $ad['post_id'] ?>, 'interested')" class="flex-1 w-full py-4 rounded-2xl font-black transition-all btn-base btn-interested">👍 مهتم</button>
                        <button onclick="handleResponse(<?= $ad['post_id'] ?>, 'not_interested')" class="flex-1 w-full py-4 rounded-2xl font-black transition-all btn-base btn-not-interested">👎 غير مهتم</button>
                        <a href="<?= htmlspecialchars($ad['post_url']) ?>" target="_blank" class="flex-1 w-full py-4 rounded-2xl font-black text-center transition-all btn-base btn-details">🔗 التفاصيل</a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>

<script>
function handleResponse(postId, action) {
    const card = document.getElementById('card-' + postId);
    const formData = new FormData();
    formData.append('post_id', postId);
    formData.append('action', action);

    fetch('', { method: 'POST', body: formData })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            card.style.opacity = '0';
            card.style.transform = 'scale(0.95)';
            setTimeout(() => {
                card.remove();
                if (document.querySelectorAll('.glass-card').length === 0) location.reload(); 
            }, 300);
        }
    });
}
</script>
</body>
</html>