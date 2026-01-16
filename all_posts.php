<?php
session_start();
require_once 'config.php';


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'interested') {
    header('Content-Type: application/json');
    $student_id = $_SESSION['user_id'];
    $post_id = intval($_POST['post_id']);
    
  
    $stmtInt = $conn->prepare("INSERT INTO student_post_interested (graduate_id, post_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE graduate_id = graduate_id");
    $stmtInt->bind_param("si", $student_id, $post_id);
    $stmtInt->execute();
    $stmtInt->close();

  
    $stmtMatch = $conn->prepare("INSERT INTO chef_post_matches (graduate_id, post_id, similarity_score, is_visible) VALUES (?, ?, 0, 0) ON DUPLICATE KEY UPDATE is_visible = 0");
    $stmtMatch->bind_param("si", $student_id, $post_id);
    
    if ($stmtMatch->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error']);
    }
    $stmtMatch->close();
    exit;
}

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

// 2. جلب الإعلانات التي لم يسبق التفاعل معها (ليست في جدول المطابقات)
$sqlAllAds = "
SELECT sp.*, 
       re.full_name AS est_name, re.type AS est_type, 
       re.location_description, re.latitude, re.longitude
FROM social_posts sp
LEFT JOIN registered_establishments re ON sp.establishment_id = re.id
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
        .bottom-left-chef { position: fixed; bottom: 0; left: 0; z-index: 100; width: 250px; pointer-events: none; }
        #detailsModal { transition: all 0.3s ease; }
        .modal-hidden { opacity: 0; pointer-events: none; transform: scale(0.95); }
        .modal-visible { opacity: 1; pointer-events: auto; transform: scale(1); }
        
        .btn-interested { background-color: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); color: #10b981; }
        .btn-interested:hover { background-color: #10b981; color: white; }

       
        .custom-scroll { max-height: 300px; overflow-y: auto; }
        .custom-scroll::-webkit-scrollbar { width: 5px; }
        .custom-scroll::-webkit-scrollbar-track { background: #1f2937; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #f59e0b; border-radius: 5px; }
    </style>
</head>
<body class="text-white min-h-screen pb-20 relative">

<div class="bottom-left-chef">
    <img src="images/girl.png" alt="Chef Character" class="w-full h-auto drop-shadow-2xl">
</div>

<header class="bg-gray-900/90 p-4 shadow-2xl border-b border-yellow-500/30 sticky top-0 z-50 backdrop-blur-md">
    <div class="max-w-7xl mx-auto flex justify-between items-center relative">
        <a href="student_dashboard.php" class="flex items-center gap-3 bg-gray-800/50 py-2 px-4 rounded-2xl border border-gray-700/50 hover:bg-gray-700/50">
            <div class="h-8 w-8 bg-yellow-500/20 rounded-full flex items-center justify-center text-xl border border-yellow-500/30">👨‍🍳</div>
            <p class="text-sm font-black text-yellow-500"><?= htmlspecialchars($student['full_name']) ?></p>
        </a>
        <h1 class="text-2xl font-black text-yellow-500 tracking-tighter uppercase absolute left-1/2 -translate-x-1/2">CHEF-LINK</h1>
        <a href="student_posts.php" class="text-xs bg-gray-800 hover:bg-yellow-500 hover:text-black px-4 py-2 rounded-xl transition-all font-bold flex items-center gap-2 border border-gray-700">
            ← العودة للمطابقة الذكية
        </a>
    </div>
</header>

<main class="max-w-4xl mx-auto p-6 space-y-10 mt-6">
    <div class="border-r-8 border-gray-600 pr-6">
        <h2 class="text-3xl font-black text-white">استكشاف الإعلانات العامة</h2>
        <p class="text-gray-400 mt-2">الإعلانات المتوفرة في النظام بعيداً عن مهاراتك المسجلة.</p>
    </div>

    <div class="grid gap-6">
        <?php if (empty($allAds)): ?>
            <div class="text-center py-20 bg-gray-900/20 rounded-3xl border-2 border-dashed border-gray-800 italic text-gray-500">
                لا توجد إعلانات إضافية حالياً..
            </div>
        <?php else: ?>
            <?php foreach ($allAds as $ad): ?>
                <div class="glass-card p-6 rounded-[1.5rem] group transition-all duration-300 shadow-lg border-r-4 border-gray-500 hover:border-yellow-500/40" id="card-<?= $ad['id'] ?>">
                    <div class="flex justify-between items-center mb-4">
                        <span class="bg-gray-800 text-yellow-500 text-[10px] font-bold px-3 py-1 rounded-full border border-gray-700 uppercase tracking-widest">
                            <?= htmlspecialchars($ad['platform']) ?>
                        </span>
                    </div>

                    <div class="text-gray-400 text-sm leading-relaxed mb-8 pr-4 group-hover:text-gray-200 transition-colors">
                        <?= nl2br(htmlspecialchars($ad['content'])) ?>
                    </div>

                    <div class="flex gap-3">
                        <button onclick="handleInterested(<?= $ad['id'] ?>)" 
                                class="flex-1 btn-interested font-bold py-4 rounded-xl transition-all transform active:scale-95 flex items-center justify-center gap-2">
                            👍 مهتم
                        </button>
                        <button onclick='openDetails(<?= json_encode($ad) ?>)' 
                               class="flex-[2] bg-gray-800 hover:bg-yellow-500 text-white hover:text-black font-bold py-4 rounded-xl flex items-center justify-center gap-3 transition-all transform active:scale-95 group/btn">
                            🔍 تفاصيل الإعلان
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>

<div id="detailsModal" class="fixed inset-0 z-[200] flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm modal-hidden">
    <div class="bg-gray-900 border border-yellow-500 w-full max-w-2xl rounded-[2.5rem] overflow-hidden shadow-2xl">
        <div class="bg-yellow-500 p-6 flex justify-between items-center text-black">
            <div>
                <h3 id="m-est-name" class="text-2xl font-black"></h3>
                <p id="m-est-type" class="text-xs font-bold uppercase tracking-widest opacity-70"></p>
            </div>
            <button onclick="closeDetails()" class="text-3xl font-black">×</button>
        </div>
        <div class="p-8 space-y-6">
            <div>
                <h4 class="text-yellow-500 font-black text-xs uppercase mb-1 italic">وصف الموقع:</h4>
                <p id="m-location-desc" class="text-gray-300 text-lg italic"></p>
            </div>
            <div class="bg-gray-800/50 p-6 rounded-2xl border border-gray-700">
                <h4 class="text-yellow-500 font-black text-xs uppercase mb-2 italic">نص الإعلان الكامل:</h4>
                <p id="m-content" class="text-white leading-relaxed custom-scroll"></p>
            </div>
            <div class="flex flex-col sm:flex-row gap-4 pt-4">
                <a id="m-map-link" href="#" target="_blank" class="flex-1 bg-blue-600 text-white py-4 rounded-2xl font-black text-center transition-all hover:bg-blue-500">📍 الخريطة</a>
                <a id="m-post-link" href="#" target="_blank" class="flex-1 bg-yellow-500 text-black py-4 rounded-2xl font-black text-center transition-all hover:bg-yellow-400">🔗 رابط الفيسبوك</a>
            </div>
        </div>
    </div>
</div>

<script>
function handleInterested(postId) {
    const card = document.getElementById('card-' + postId);
    const formData = new FormData();
    formData.append('post_id', postId);
    formData.append('action', 'interested');

    fetch('', { method: 'POST', body: formData })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            card.style.transition = 'all 0.5s ease';
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            setTimeout(() => {
                card.remove();
                if (document.querySelectorAll('.glass-card').length === 0) location.reload();
            }, 500);
        } else {
            alert("حدث خطأ أثناء حفظ الاهتمام");
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
    const modal = document.getElementById('detailsModal');
    modal.classList.add('modal-hidden');
    modal.classList.remove('modal-visible');
}

window.onclick = function(event) {
    const modal = document.getElementById('detailsModal');
    if (event.target == modal) closeDetails();
}
</script>

</body>
</html>