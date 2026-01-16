<?php
session_start();
require_once 'config.php';


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $student_id = $_SESSION['user_id'];
    $post_id = intval($_POST['post_id']);
    
    $updateStmt = $conn->prepare("UPDATE chef_post_matches SET is_visible = 0 WHERE graduate_id = ? AND post_id = ?");
    $updateStmt->bind_param("si", $student_id, $post_id);
    $updateStmt->execute();
    $updateStmt->close();

    $success = false;
    if ($_POST['action'] === 'interested') {
        $stmt = $conn->prepare("INSERT INTO student_post_interested (graduate_id, post_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE graduate_id = graduate_id");
        $stmt->bind_param("si", $student_id, $post_id);
        $success = $stmt->execute();
        $stmt->close();
    } elseif ($_POST['action'] === 'not_interested') {
        $success = true; 
    }
    
    if ($success) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error']);
    }
    exit;
}


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

$sqlAds = "SELECT sp.id AS post_id, sp.content, sp.post_url, sp.platform, 
           cpm.similarity_score, cpm.matched_at,
           re.full_name AS est_name, re.type AS est_type, 
           re.location_description, re.latitude, re.longitude
FROM chef_post_matches cpm
JOIN social_posts sp ON sp.id = cpm.post_id
LEFT JOIN registered_establishments re ON sp.establishment_id = re.id
WHERE cpm.graduate_id = ? 
AND cpm.is_visible = 1
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
        .btn-base { background-color: rgba(55, 65, 81, 0.3); border: 1px solid rgba(75, 85, 99, 0.4); color: #9ca3af; transition: all 0.3s ease; }
        .btn-interested:hover { background-color: #10b981; color: white; border-color: #10b981; }
        .btn-not-interested:hover { background-color: #ef4444; color: white; border-color: #ef4444; }
        .btn-details:hover { background-color: #f59e0b; color: #000; border-color: #f59e0b; }
        #detailsModal { transition: all 0.3s ease; }
        .modal-hidden { opacity: 0; pointer-events: none; transform: scale(0.95); }
        .modal-visible { opacity: 1; pointer-events: auto; transform: scale(1); }
        .custom-scroll { max-height: 350px; overflow-y: auto; }
        .custom-scroll::-webkit-scrollbar { width: 5px; }
        .custom-scroll::-webkit-scrollbar-track { background: #1f2937; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #f59e0b; border-radius: 5px; }
    </style>
</head>
<body class="text-white min-h-screen pb-24 relative">

<div class="bottom-left-chef"><img src="images/girl.png" alt="Chef" class="w-full h-auto drop-shadow-2xl"></div>

<div class="fixed top-24 right-6 z-50 hidden md:block">
    <a href="all_posts.php" class="flex items-center gap-3 bg-gray-900/90 backdrop-blur-md border-r-4 border-yellow-500 text-white px-6 py-4 rounded-xl shadow-2xl hover:bg-yellow-500 hover:text-black transition-all duration-300">
        <span class="text-base font-bold">استكشاف كافة الإعلانات</span>
    </a>
</div>

<header class="bg-gray-900/90 p-4 shadow-2xl border-b border-yellow-500/30 sticky top-0 z-50 backdrop-blur-md">
    <div class="max-w-7xl mx-auto flex justify-between items-center relative">
        <a href="student_dashboard.php" class="flex items-center gap-3 bg-gray-800/50 py-2 px-4 rounded-2xl border border-gray-700/50">
            <div class="h-8 w-8 bg-yellow-500/20 rounded-full flex items-center justify-center text-xl">👨‍🍳</div>
            <p class="text-sm font-black text-yellow-500"><?= htmlspecialchars($student['full_name']) ?></p>
        </a>
        <h1 class="text-xl md:text-2xl font-black text-yellow-500 uppercase absolute left-1/2 -translate-x-1/2">CHEF-LINK</h1>
        <a href="waiting_list.php" class="bg-gray-800 hover:bg-green-600 text-green-500 hover:text-white border border-green-500/30 px-4 py-2 rounded-xl transition-all font-bold text-xs">⭐ المهتم بها</a>
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
    </div>

    <div class="bg-blue-600/20 p-6 rounded-3xl border border-blue-500/30 backdrop-blur-sm">
        <div class="flex items-center gap-4 mb-4">
            <div class="h-10 w-10 bg-blue-500 rounded-full flex items-center justify-center text-xl">💡</div>
            <h3 class="text-xl font-bold text-blue-400">تحليل مهاراتك الحالية</h3>
        </div>
        <p class="text-gray-300 leading-relaxed italic">
    "يقوم نظامنا الذكي حالياً بتحليل مهاراتك: <span class="text-blue-400 font-bold"><?= htmlspecialchars($student['skills']) ?></span> ومطابقتها مع الفرص المتاحة. لضمان أدق النتائج، <span class="text-red font-bold ">احرص على إضافة كافة مهاراتك في ملفك الشخصي وكتابتها مفصولة بفواصل (،)</span>."
</p>
    </div>

    <div id="matches-container" class="grid gap-8">
        <?php foreach ($ads as $ad): ?>
            <div class="glass-card p-8 rounded-[2.5rem] group relative" id="card-<?= $ad['post_id'] ?>">
                <div class="flex justify-between items-start mb-6">
                    <div class="w-full max-w-[250px]">
                        <div class="flex justify-between text-xs mb-2 font-black text-yellow-500">
                            <span>قوة المطابقة</span>
                            <span><?= number_format($ad['similarity_score'] * 100, 0) ?>%</span>
                        </div>
                        <div class="score-bar"><div class="score-fill" style="width: <?= $ad['similarity_score'] * 100 ?>%"></div></div>
                    </div>
                </div>
                <div class="text-gray-200 text-lg leading-relaxed mb-8 pr-6 border-r-4 border-gray-700">
                    <?= nl2br(htmlspecialchars($ad['content'])) ?>
                </div>
                <div class="flex flex-col sm:flex-row items-center gap-4">
                    <button onclick="handleResponse(<?= $ad['post_id'] ?>, 'interested')" class="flex-1 w-full py-4 rounded-2xl font-black btn-base btn-interested">👍 مهتم</button>
                    <button onclick="handleResponse(<?= $ad['post_id'] ?>, 'not_interested')" class="flex-1 w-full py-4 rounded-2xl font-black btn-base btn-not-interested">👎 غير مهتم</button>
                    <button onclick='openDetails(<?= json_encode($ad) ?>)' class="flex-1 w-full py-4 rounded-2xl font-black btn-base btn-details">🔗 التفاصيل</button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</main>

<div id="detailsModal" class="fixed inset-0 z-[200] flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm modal-hidden">
    <div class="bg-gray-900 border border-yellow-500 w-full max-w-2xl rounded-[2.5rem] overflow-hidden shadow-2xl">
        <div class="bg-yellow-500 p-6 flex justify-between items-center text-black">
            <h3 id="m-est-name" class="text-2xl font-black"></h3>
            <button onclick="closeDetails()" class="text-3xl font-black">×</button>
        </div>
        <div class="p-8 space-y-6">
            <p id="m-content" class="text-white leading-relaxed bg-gray-800/50 p-6 rounded-2xl custom-scroll"></p>
            <div class="flex flex-col sm:flex-row gap-4">
                <a id="m-map-link" href="#" target="_blank" class="flex-1 bg-blue-600 text-white py-4 rounded-2xl font-black text-center">📍 الخريطة</a>
                <a id="m-post-link" href="#" target="_blank" class="flex-1 bg-yellow-500 text-black py-4 rounded-2xl font-black text-center">🔗 الفيسبوك</a>
            </div>
        </div>
    </div>
</div>

<script>
function handleResponse(postId, action) {
    const card = document.getElementById('card-' + postId);
    const formData = new FormData();
    formData.append('post_id', postId);
    formData.append('action', action);
    fetch('', { method: 'POST', body: formData }).then(r => r.json()).then(data => {
        if (data.status === 'success') {
            card.style.opacity = '0';
            card.style.transform = 'translateX(100px)';
            setTimeout(() => { card.remove(); if (!document.querySelector('.glass-card')) location.reload(); }, 500);
        }
    });
}
function openDetails(ad) {
    document.getElementById('m-est-name').innerText = ad.est_name || 'مطعم';
    document.getElementById('m-content').innerText = ad.content;
    
   
    const platformNoticeId = 'm-platform-notice';
    let noticeElem = document.getElementById(platformNoticeId);
    
    if (!noticeElem) {
        noticeElem = document.createElement('div');
        noticeElem.id = platformNoticeId;
        noticeElem.className = "mb-4 p-3 bg-green-500/20 border border-green-500/50 rounded-xl text-green-400 text-xs font-bold flex items-center gap-2";
        document.getElementById('m-content').before(noticeElem);
    }

 
    const facebookBtn = document.getElementById('m-post-link');

   
    if (ad.platform === 'Dashboard') {
 
        noticeElem.innerHTML = "<span>🌐</span> هذا الإعلان تم نشره مباشرة عبر منصة شيف لينك";
        noticeElem.style.display = 'flex';
     
        facebookBtn.style.display = 'none';
    } else {
  
        noticeElem.style.display = 'none';
    
        facebookBtn.style.display = 'block'; 
        facebookBtn.href = ad.post_url;
    }

    document.getElementById('m-map-link').href = `https://www.google.com/maps?q=${ad.latitude},${ad.longitude}`;
    document.getElementById('detailsModal').classList.replace('modal-hidden', 'modal-visible');
}
function closeDetails() {
    document.getElementById('detailsModal').classList.replace('modal-visible', 'modal-hidden');
}
</script>
</body>
</html>