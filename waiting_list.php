<?php
session_start();
require_once 'config.php';


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_interest') {
    header('Content-Type: application/json');
    $student_id = $_SESSION['user_id'];
    $post_id = intval($_POST['post_id']);
    
    $stmt = $conn->prepare("DELETE FROM student_post_interested WHERE graduate_id = ? AND post_id = ?");
    $stmt->bind_param("si", $student_id, $post_id);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error']);
    }
    $stmt->close();
    exit;
}

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    header("Location: login.php");
    exit;
}

$national_id = $_SESSION['user_id'];
$conn->set_charset("utf8mb4");

$student_query = $conn->prepare("SELECT full_name FROM students WHERE national_id = ?");
$student_query->bind_param("s", $national_id);
$student_query->execute();
$student = $student_query->get_result()->fetch_assoc();

// جلب المنشورات مع بيانات المؤسسة للخريطة
$sqlInterested = "SELECT sp.id AS post_id, sp.content, sp.post_url, sp.platform, 
                 re.full_name AS est_name, re.latitude, re.longitude
FROM student_post_interested spi
JOIN social_posts sp ON sp.id = spi.post_id
LEFT JOIN registered_establishments re ON sp.establishment_id = re.id
WHERE spi.graduate_id = ? 
ORDER BY spi.id DESC";

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
        body { font-family: 'Tajawal', sans-serif; background-color: #111827; min-height: 100vh; }
        .glass-card { background: rgba(31, 41, 55, 0.6); backdrop-filter: blur(8px); border: 1px solid rgba(16, 185, 129, 0.2); transition: all 0.3s ease; }
        .bottom-left-chef { position: fixed; bottom: 20px; left: 0; z-index: 50; width: 250px; pointer-events: none; }
        .btn-remove { background-color: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); color: #ef4444; }
        .btn-remove:hover { background-color: #ef4444; color: white; }
        
        /* المودال */
        #detailsModal { transition: all 0.3s ease; }
        .modal-hidden { opacity: 0; pointer-events: none; transform: scale(0.95); }
        .modal-visible { opacity: 1; pointer-events: auto; transform: scale(1); }
        .custom-scroll { max-height: 350px; overflow-y: auto; }
        .custom-scroll::-webkit-scrollbar { width: 5px; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #f59e0b; border-radius: 5px; }
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
            <a href="student_posts.php" class="bg-gray-800 hover:bg-gray-700 text-white px-5 py-2 rounded-2xl text-xs font-bold transition">← العودة للمطابقات</a>
        </div>
    </header>

    <div class="bottom-left-chef hidden md:block">
        <img src="images/time.png" alt="Chef Character" class="w-full h-auto drop-shadow-2xl">
    </div>

    <main class="max-w-4xl w-full mx-auto p-6">
        <div class="flex flex-col md:flex-row justify-between items-center mb-10 bg-gray-900/50 p-6 rounded-3xl border border-gray-800 gap-4">
            <div class="text-center md:text-right">
                <h1 class="text-2xl font-black text-white italic">المنشورات المهتم بها</h1>
                <p class="text-gray-400 text-sm">الوظائف التي أبدت اهتمامك بها وتنتظر تواصل المنشآت معك.</p>
            </div>
            <div class="bg-green-500/10 text-green-400 px-4 py-2 rounded-xl border border-green-500/20 font-bold text-sm">
                عدد العناصر: <span id="item-count"><?= count($ads) ?></span>
            </div>
        </div>
        
        <div class="grid gap-6" id="ads-container">
            <?php foreach ($ads as $ad): ?>
                <div class="glass-card p-6 rounded-[2rem] border-r-4 border-green-500 shadow-xl relative transition" id="card-<?= $ad['post_id'] ?>">
                    <div class="flex justify-between items-start mb-4">
                        <span class="text-[10px] bg-green-500/20 text-green-400 px-3 py-1 rounded-full font-bold uppercase tracking-wider">مهتم بها ✅</span>
                    </div>

                    <div class="text-gray-200 text-md leading-relaxed mb-6 italic pr-4 border-r-2 border-gray-700">
                        "<?= nl2br(htmlspecialchars(mb_strimwidth($ad['content'], 0, 150, "..."))) ?>"
                    </div>

                    <div class="flex flex-col sm:flex-row gap-3">
                        <button onclick='openDetails(<?= json_encode($ad) ?>)' 
                                class="flex-[2] bg-green-600 hover:bg-green-500 text-white text-center py-3 rounded-xl font-bold transition text-sm shadow-lg shadow-green-900/20">
                            🔗 التفاصيل
                        </button>
                        <button onclick="removeInterest(<?= $ad['post_id'] ?>)" 
                                class="flex-1 btn-remove text-center py-3 rounded-xl font-bold transition text-sm">
                            إلغاء الاهتمام
                        </button>
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
    function removeInterest(postId) {
        if (!confirm('هل تريد إلغاء الاهتمام بهذا المنشور وحذفه؟')) return;
        const formData = new FormData();
        formData.append('post_id', postId);
        formData.append('action', 'remove_interest');

        fetch('', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                const card = document.getElementById('card-' + postId);
                card.style.opacity = '0';
                card.style.transform = 'scale(0.9)';
                setTimeout(() => {
                    card.remove();
                    const countSpan = document.getElementById('item-count');
                    countSpan.innerText = parseInt(countSpan.innerText) - 1;
                    if (document.querySelectorAll('.glass-card').length === 0) location.reload(); 
                }, 300);
            }
        });
    }

    function openDetails(ad) {
        document.getElementById('m-est-name').innerText = ad.est_name || 'مطعم / منشأة';
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