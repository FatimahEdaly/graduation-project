<?php

if (ob_get_level() === 0) ob_start(); 
session_start();
require_once 'config.php';

// التحقق من الجلسة (يجب أن يكون نوع المستخدم مؤسسة)
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'establishment') {
    header("Location: login.php");
    exit;
}

$establishment_id = $_SESSION['user_id'];
$conn->set_charset("utf8mb4");

// جلب بيانات المركز للفوتر والهيدر
$center_result = $conn->query("SELECT * FROM centers LIMIT 1");
$center = $center_result ? $center_result->fetch_assoc() : [];

// جلب بيانات المؤسسة
$stmt = $conn->prepare("SELECT * FROM registered_establishments WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $establishment_id);
$stmt->execute();
$establishment = $stmt->get_result()->fetch_assoc();

if (!$establishment) {
    session_destroy();
    header("Location:login.php");
    exit;
}

// التحقق من إكمال البيانات (أول دخول إجباري)
if (intval($establishment['is_first_login']) === 0) {
    header("Location: complete_establishment_profile.php");
    exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    // أ: رفع صورة المؤسسة
    if ($_POST['action'] === 'upload_profile') {
        $file = $_FILES['profile_image'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $uploadDir = __DIR__ . '/uploads/establishment_img/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $newFileName = 'res_'.$establishment_id.'_'.time().'.'.$ext;
        $uploadPath = $uploadDir.$newFileName;

       
        if (!empty($establishment['profile_image']) && file_exists(__DIR__.'/'.$establishment['profile_image'])) {
            unlink(__DIR__.'/'.$establishment['profile_image']);
        }

        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
            $filePathDB = 'uploads/establishment_img/'.$newFileName;
            $conn->query("UPDATE registered_establishments SET profile_image = '$filePathDB' WHERE id = $establishment_id");
            echo json_encode(['success'=>true,'file_path'=>$filePathDB]);
        } else {
            echo json_encode(['success'=>false,'message'=>'خطأ في الرفع']);
        }
        exit;
    }

    // ب: تغيير كلمة المرور
    if ($_POST['action'] === 'change_password') {
        $current = $_POST['current'] ?? '';
        $newPass = $_POST['newPass'] ?? '';
        if (!password_verify($current, $establishment['password'])) {
            echo json_encode(['success'=>false,'message'=>'كلمة المرور الحالية خاطئة']);
            exit;
        }
        $newHash = password_hash($newPass, PASSWORD_DEFAULT);
        $upd = $conn->prepare("UPDATE registered_establishments SET password = ? WHERE id = ?");
        $upd->bind_param("si", $newHash, $establishment_id);
        echo json_encode(['success'=>$upd->execute()]);
        exit;
    }

    // ج: نشر وظيفة
    if ($_POST['action'] === 'add_job_post') {
        $content = trim($_POST['content'] ?? '');
        $stmtP = $conn->prepare("INSERT INTO social_posts (establishment_id, platform, content, created_at, is_processed) VALUES (?, 'Dashboard', ?, NOW(), 0)");
        $stmtP->bind_param("is", $establishment_id, $content);
        echo json_encode(['success'=>$stmtP->execute()]);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>لوحة التحكم - <?php echo htmlspecialchars($establishment['full_name']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <style>
        body::-webkit-scrollbar { display: none; }
        body { -ms-overflow-style: none; scrollbar-width: none; font-family: 'Tajawal', sans-serif; }
    </style>
</head>
<body class="min-h-screen flex flex-col bg-gray-800 text-white relative">

<div class="absolute inset-0">
    <img src="image/kitchens.png" class="w-full h-full object-cover filter brightness-50" alt="خلفية">
</div>
<div class="absolute inset-0 bg-black/40"></div>

<div class="relative flex flex-col min-h-screen z-10">

    <header class="bg-gray-800/70 backdrop-blur-md shadow-md border-b border-yellow-400/30 p-4 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto flex justify-between items-center relative">
            
            <div class="flex items-center gap-3 bg-gray-800/50 py-2 px-4 rounded-2xl border border-gray-700/50 hover:border-yellow-500/50 transition-all duration-300 group">
                <div class="h-8 w-8 bg-yellow-500/20 rounded-full flex items-center justify-center text-xl border border-yellow-500/30 group-hover:scale-110 transition-transform">🏢</div>
                <div class="text-right">
                    <p class="text-sm font-black text-yellow-300 leading-none group-hover:text-yellow-400"><?php echo htmlspecialchars($establishment['full_name']); ?></p>
                </div>
            </div>

            <div class="absolute left-1/2 -translate-x-1/2">
                <h1 class="text-2xl font-black text-yellow-400 tracking-tighter uppercase">CHEF-LINK</h1>
            </div>

            <div class="flex items-center">
                <a href="logout.php" class="text-xs bg-red-500/10 hover:bg-red-500 hover:text-white text-red-400 px-4 py-2 rounded-xl transition-all font-bold border border-red-500/20">تسجيل الخروج</a>
            </div>
        </div>
    </header>

    <main class="flex-grow flex flex-col items-center justify-center text-center px-4 py-12">
        <div class="relative group">
            <img id="establishmentImage" 
                 src="<?php echo !empty($establishment['profile_image']) ? $establishment['profile_image'] : 'uploads/default-avatar.jpg'; ?>" 
                 class="w-36 h-36 rounded-full border-4 border-yellow-400 shadow-lg object-cover transition-transform duration-300 group-hover:scale-105 cursor-pointer">
            <div id="uploadIcon" class="absolute bottom-0 right-0 bg-yellow-400 text-gray-900 rounded-full w-10 h-10 flex items-center justify-center text-2xl cursor-pointer hover:bg-yellow-500 transition">+</div>
            <input type="file" id="profileInput" class="hidden" accept="image/*">
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-6 mt-10 max-w-4xl">
            <button id="addPostBtn" class="flex flex-col items-center justify-center bg-gray-800/70 hover:bg-yellow-400 hover:text-gray-900 w-40 h-40 rounded-2xl shadow-xl transition-all duration-300 backdrop-blur-md">
                <span class="text-5xl">📢</span>
                <span class="text-sm font-semibold mt-1">نشر وظيفة</span>
            </button>

            <a href="complete_establishment_profile.php" class="flex flex-col items-center justify-center bg-gray-800/70 hover:bg-yellow-400 hover:text-gray-900 w-40 h-40 rounded-2xl shadow-xl transition-all duration-300 backdrop-blur-md">
                <span class="text-5xl">📝</span>
                <span class="text-sm font-semibold mt-1">تعديل البيانات</span>
            </a>

            <button id="changePassBtn" class="flex flex-col items-center justify-center bg-gray-800/70 hover:bg-yellow-400 hover:text-gray-900 w-40 h-40 rounded-2xl shadow-xl transition-all duration-300 backdrop-blur-md">
                <span class="text-5xl">🔒</span>
                <span class="text-sm font-semibold mt-1">تغيير كلمة السر</span>
            </button>

            <button onclick="window.location.href='establishment_posts.php'" class="flex flex-col items-center justify-center bg-gray-800/70 hover:bg-yellow-400 hover:text-gray-900 w-40 h-40 rounded-2xl shadow-xl transition-all duration-300 backdrop-blur-md">
                <span class="text-5xl">📊</span>
                <span class="text-sm font-semibold mt-1">منشوراتي</span>
            </button>
        </div>
    </main>

    <footer class="relative z-10 bg-gray-800/80 text-yellow-300 py-4 mt-6 border-t border-yellow-400/20">
        <div class="max-w-6xl mx-auto grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 text-xs px-4 text-right">
            <div>
                <h3 class="text-base font-semibold mb-1">📞 معلومات التواصل</h3>
                <p>الهاتف: <?php echo htmlspecialchars($center['phone']); ?></p>
                <p>واتساب: <a href="https://wa.me/<?php echo $center['whatsapp']; ?>" class="text-green-400 hover:underline"><?php echo $center['whatsapp']; ?></a></p>
            </div>
            <div>
                <h3 class="text-base font-semibold mb-1">🌐 السوشيال ميديا</h3>
                <a href="<?php echo $center['facebook']; ?>" class="block hover:underline" target="_blank">Facebook</a>
                <a href="<?php echo $center['instagram']; ?>" class="block hover:underline" target="_blank">Instagram</a>
            </div>
            <div>
                <h3 class="text-base font-semibold mb-1">📍 الموقع</h3>
                <a href="https://maps.google.com/?q=<?php echo $center['latitude'].','.$center['longitude']; ?>" target="_blank" class="inline-block mt-1 px-3 py-1 bg-yellow-400 text-gray-900 font-bold rounded hover:bg-yellow-500 transition">فتح الخريطة</a>
            </div>
        </div>
        <div class="text-center mt-2 text-gray-400 text-xs italic">
            © <?php echo date('Y'); ?> جميع الحقوق محفوظة - <?php echo htmlspecialchars($center['name']); ?>
        </div>
    </footer>
</div>

<div id="passModal" class="hidden fixed inset-0 bg-black/60 flex items-center justify-center z-[100]">
    <div class="bg-gray-900 border border-yellow-400/30 rounded-2xl p-6 w-full max-w-sm text-right">
        <h2 class="text-lg font-bold text-yellow-400 mb-4 text-center">تغيير كلمة المرور</h2>
        <input type="password" id="currentPass" placeholder="القديمة" class="w-full p-2 mb-3 rounded bg-gray-800 border border-gray-700 outline-none focus:ring-1 focus:ring-yellow-400">
        <input type="password" id="newPass" placeholder="الجديدة" class="w-full p-2 mb-4 rounded bg-gray-800 border border-gray-700 outline-none focus:ring-1 focus:ring-yellow-400">
        <button id="savePass" class="w-full bg-yellow-400 text-gray-900 font-bold py-2 rounded-lg hover:bg-yellow-500 transition">حفظ</button>
        <button onclick="$('#passModal').addClass('hidden')" class="w-full mt-2 text-gray-400 text-sm">إلغاء</button>
    </div>
</div>

<div id="postModal" class="hidden fixed inset-0 bg-black/60 flex items-center justify-center z-[100]">
    <div class="bg-gray-900 border border-yellow-400/30 rounded-2xl p-6 w-full max-w-lg text-right">
        <h2 class="text-lg font-bold text-yellow-400 mb-4">📢 نشر إعلان وظيفي</h2>
        <textarea id="jobContent" rows="4" class="w-full p-3 rounded-xl bg-gray-800 border border-gray-700 outline-none focus:ring-1 focus:ring-yellow-400 text-sm" placeholder="اكتب تفاصيل الوظيفة..."></textarea>
        <button id="savePost" class="w-full mt-4 bg-yellow-400 text-gray-900 font-bold py-2 rounded-lg hover:bg-yellow-500 transition">نشر الآن</button>
        <button onclick="$('#postModal').addClass('hidden')" class="w-full mt-2 text-gray-400 text-sm">إلغاء</button>
    </div>
</div>

<script>

$('#uploadIcon').click(() => $('#profileInput').click());
$('#profileInput').change(function() {
    const file = this.files[0];
    const formData = new FormData();
    formData.append('action', 'upload_profile');
    formData.append('profile_image', file);
    $.ajax({
        url: '', type: 'POST', data: formData, contentType: false, processData: false,
        success: (res) => { if(res.success) { $('#establishmentImage').attr('src', res.file_path); alert('✅ تم التحديث'); } }
    });
});


$('#changePassBtn').click(() => $('#passModal').removeClass('hidden'));
$('#addPostBtn').click(() => $('#postModal').removeClass('hidden'));

// حفظ كلمة المرور
$('#savePass').click(function() {
    $.post('', {action:'change_password', current:$('#currentPass').val(), newPass:$('#newPass').val()}, (res) => {
        if(res.success) { alert('✅ تم التغيير'); $('#passModal').addClass('hidden'); } else { alert('❌ ' + res.message); }
    });
});

// حفظ المنشور
$('#savePost').click(function() {
    $.post('', {action:'add_job_post', content:$('#jobContent').val()}, (res) => {
        if(res.success) { alert('✅ تم النشر'); $('#postModal').addClass('hidden'); $('#jobContent').val(''); }
    });
});
</script>
</body>
</html>