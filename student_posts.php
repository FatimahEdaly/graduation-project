<?php
session_start();
require_once 'config.php';

// --- الجزء الأول: منطق الحذف (سيتم تنفيذه فقط عند الضغط على زر لا يهمني) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'dismiss') {
    header('Content-Type: application/json');
    $student_id = $_SESSION['user_id'];
    $post_id = intval($_POST['post_id']);

    // حذف المطابقة من جدول المقارنات
    $stmt = $conn->prepare("DELETE FROM chef_post_matches WHERE graduate_id = ? AND post_id = ?");
    $stmt->bind_param("si", $student_id, $post_id);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error']);
    }
    $stmt->close();
    exit; // توقف هنا ولا تكمل تحميل باقي الصفحة
}

// --- الجزء الثاني: جلب البيانات للعرض الطبيعي ---
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    header("Location: login.php");
    exit;
}

$national_id = $_SESSION['user_id'];
$conn->set_charset("utf8mb4");

// جلب بيانات الطالب
$stmtStudent = $conn->prepare("SELECT full_name, skills FROM students WHERE national_id = ? LIMIT 1");
$stmtStudent->bind_param("s", $national_id);
$stmtStudent->execute();
$student = $stmtStudent->get_result()->fetch_assoc();
$stmtStudent->close();

// جلب الإعلانات المطابقة
$sqlAds = "SELECT sp.id AS post_id, sp.content, sp.post_url, sp.platform, cpm.similarity_score, cpm.matched_at
FROM chef_post_matches cpm
JOIN social_posts sp ON sp.id = cpm.post_id
WHERE cpm.graduate_id = ?
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
    <title>شيف لينك | فرصك المختارة بدقة</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Tajawal', sans-serif; background-color: #111827; }
        .score-bar { height: 8px; border-radius: 10px; background-color: #374151; overflow: hidden; }
        .score-fill { height: 100%; background: linear-gradient(90deg, #f59e0b, #fbbf24); transition: width 1.5s ease-out; }
        .pulse-badge { animation: pulse-animation 2s infinite; }
        @keyframes pulse-animation { 
            0% { box-shadow: 0 0 0 0px rgba(245, 158, 11, 0.6); }
            100% { box-shadow: 0 0 0 12px rgba(245, 158, 11, 0); }
        }
        .glass-card { background: rgba(31, 41, 55, 0.6); backdrop-filter: blur(8px); border: 1px solid rgba(75, 85, 99, 0.4); transition: all 0.4s ease; }
    </style>
</head>
<body class="text-white min-h-screen pb-24 relative">

<div class="fixed top-24 right-6 z-50 hidden md:block">
    <a href="all_posts.php" class="flex items-center gap-3 bg-gray-900/90 backdrop-blur-md border-r-4 border-yellow-500 text-white px-5 py-3 rounded-xl shadow-2xl hover:bg-yellow-500 hover:text-black transition-all duration-300 group">
        <span class="text-sm font-bold">كافة الإعلانات</span>
    </a>
</div>

<header class="bg-gray-900/90 p-6 shadow-2xl border-b border-yellow-500/30 sticky top-0 z-50 backdrop-blur-md text-center">
    <h1 class="text-3xl font-black text-yellow-500 tracking-widest uppercase">CHEF-LINK</h1>
</header>

<main class="max-w-4xl mx-auto p-6 space-y-10 mt-4">
    <div class="bg-gray-900 p-8 rounded-[2rem] border-r-[10px] border-yellow-500 shadow-2xl flex flex-col md:flex-row md:items-center justify-between gap-6 relative overflow-hidden">
        <div>
            <h2 class="text-3xl font-black text-white mb-2">فرصك المختارة بدقة</h2>
            <p class="text-gray-400 text-sm italic">بناءً على مهاراتك: "<?= htmlspecialchars($student['skills']) ?>"</p>
        </div>
        <div class="pulse-badge bg-yellow-500 text-black px-8 py-3 rounded-2xl font-black text-xl shadow-lg">
            <?= count($ads) ?>
        </div>
    </div>

    <div id="matches-container" class="grid gap-8">
        <?php if (empty($ads)): ?>
            <div class="text-center py-20 bg-gray-900/20 rounded-3xl border-2 border-dashed border-gray-800 text-gray-500">لا يوجد مطابقات حالياً..</div>
        <?php else: ?>
            <?php foreach ($ads as $ad): ?>
                <div class="glass-card p-6 rounded-[2rem] group relative">
                    <div class="flex justify-between items-start mb-6">
                        <div class="w-full max-w-[200px]">
                            <div class="flex justify-between text-xs mb-1 font-bold text-yellow-500"><span>نسبة المطابقة</span><span><?= number_format($ad['similarity_score'] * 100, 0) ?>%</span></div>
                            <div class="score-bar"><div class="score-fill" style="width: <?= $ad['similarity_score'] * 100 ?>%"></div></div>
                        </div>
                        <span class="text-[10px] text-gray-500"><?= date('Y-m-d', strtotime($ad['matched_at'])) ?></span>
                    </div>

                    <div class="text-gray-300 text-sm leading-relaxed mb-8 pr-5 border-r-2 border-gray-700/50 group-hover:border-yellow-500 transition-colors">
                        <?= nl2br(htmlspecialchars($ad['content'])) ?>
                    </div>

                    <div class="flex flex-col sm:flex-row items-center gap-4">
                        <a href="<?= htmlspecialchars($ad['post_url']) ?>" target="_blank" class="flex-[3] w-full bg-yellow-500 hover:bg-yellow-400 text-black font-black py-4 rounded-2xl flex items-center justify-center gap-3 transition-all shadow-lg shadow-yellow-500/10">
                            <span>الانتقال لمصدر الإعلان</span>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                        </a>
                        
                        <button onclick="dismissMatch(<?= $ad['post_id'] ?>, this)" class="flex-1 w-full bg-gray-800 hover:bg-red-600 text-gray-400 hover:text-white py-4 rounded-2xl transition-all flex items-center justify-center gap-2 border border-gray-700">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                            <span class="text-xs font-bold">لا يهمني</span>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>

<script>
function dismissMatch(postId, btnElement) {
    if (!confirm('هل تريد إزالة هذه الفرصة؟')) return;

    const card = btnElement.closest('.glass-card');
    const formData = new FormData();
    formData.append('post_id', postId);
    formData.append('action', 'dismiss'); // إضافة علامة للأكشن

    // الطلب يتم إرساله لنفس الصفحة الحالية ''
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px) scale(0.9)';
            setTimeout(() => {
                card.remove();
                if (document.querySelectorAll('.glass-card').length === 0) {
                    location.reload(); 
                }
            }, 400);
        } else {
            alert('حدث خطأ في معالجة الطلب.');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('حدث خطأ في الاتصال بالخادم.');
    });
}
</script>

<footer class="text-center p-12 text-gray-600 text-[10px] tracking-widest uppercase">&copy; 2026 Chef-Link Platform</footer>
</body>
</html>