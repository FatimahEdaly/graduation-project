<?php
// ==========================================================
// 1. تفعيل المخزن المؤقت (Output Buffering)
// ==========================================================
if (ob_get_level() === 0) ob_start(); 

session_start();
require_once 'config.php';

// ==========================================================
// التحقق من الجلسة
// ==========================================================
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    header("Location: login.php");
    exit;
}

$national_id = $_SESSION['user_id'];
$conn->set_charset("utf8mb4");

// جلب بيانات المركز
$center_result = $conn->query("SELECT * FROM centers LIMIT 1");
$center = $center_result ? $center_result->fetch_assoc() : ['name' => 'المركز', 'image_path' => 'uploads/default-avatar.jpg', 'phone' => '', 'mobile' => '', 'whatsapp' => '', 'email' => '', 'facebook' => '', 'instagram' => '', 'latitude' => '', 'longitude' => '', 'profile_file_path' => ''];

// جلب بيانات الطالب (تم التأكد من جلب full_name للهيدر)
$stmt = $conn->prepare("SELECT * FROM students WHERE national_id = ? LIMIT 1");
$stmt->bind_param("s", $national_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    session_destroy();
    header("Location:login.php");
    exit;
}

$student = $result->fetch_assoc();

// ==================== رفع صورة شخصية (AJAX Handler) ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_profile') {
    header('Content-Type: application/json');

    if (!isset($_FILES['profile_image']) || $_FILES['profile_image']['error'] !== 0) {
        echo json_encode(['success'=>false,'message'=>'لم يتم اختيار أي ملف أو حدث خطأ.']);
        exit;
    }

    $file = $_FILES['profile_image'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','gif'];

    if (!in_array($ext, $allowed)) {
        echo json_encode(['success'=>false,'message'=>'صيغة الملف غير مدعومة.']);
        exit;
    }

    $uploadDir = __DIR__ . '/uploads/profile_img/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $newFileName = 'profile_'.$national_id.'_'.time().'.'.$ext;
    $uploadPath = $uploadDir.$newFileName;

    $oldFile = $conn->query("SELECT * FROM student_files WHERE student_id='$national_id' AND file_type='profile_image' LIMIT 1")->fetch_assoc();
    if ($oldFile) {
        $oldFilePath = __DIR__ . '/' . $oldFile['file_path'];
        if (file_exists($oldFilePath) && strpos($oldFile['file_path'], 'default-avatar') === false) {
             unlink($oldFilePath);
        }
        $conn->query("DELETE FROM student_files WHERE id=".$oldFile['id']);
    }

    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        $filePathDB = 'uploads/profile_img/'.$newFileName;
        $stmtInsert = $conn->prepare("INSERT INTO student_files (student_id, file_type, file_path) VALUES (?, 'profile_image', ?)");
        $stmtInsert->bind_param("ss", $national_id, $filePathDB);
        $stmtInsert->execute();

        echo json_encode(['success'=>true,'file_path'=>$filePathDB]);
        exit;
    } else {
        echo json_encode(['success'=>false,'message'=>'حدث خطأ أثناء رفع الصورة.']);
        exit;
    }
}

// ==================== تغيير كلمة المرور (AJAX Handler) ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $current = trim($_POST['current'] ?? '');
    $newPass = trim($_POST['newPass'] ?? '');

    header('Content-Type: application/json');

    if (strlen($newPass) < 6) {
        echo json_encode(['success'=>false,'message'=>'كلمة المرور قصيرة جداً (الحد الأدنى 6 أحرف).']);
        exit;
    }

    $hashed = $student['password']; 

    if (!password_verify($current, $hashed)) {
        echo json_encode(['success'=>false,'message'=>'كلمة المرور الحالية غير صحيحة.']);
        exit;
    }

    if (password_verify($newPass, $hashed) || ($newPass === $hashed)) {
        echo json_encode(['success'=>false,'message'=>'كلمة المرور الجديدة يجب أن تختلف عن القديمة.']);
        exit;
    }

    $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
    $newHash = password_hash($newPass, $algo);

    $update = $conn->prepare("UPDATE students SET password = ? WHERE national_id = ?");
    $update->bind_param("ss", $newHash, $national_id);
    if ($update->execute()) {
        echo json_encode(['success'=>true]);
    } else {
        echo json_encode(['success'=>false,'message'=>'حدث خطأ أثناء تحديث كلمة المرور في قاعدة البيانات.']);
    }
    exit;
}

// جلب مسار الصورة
$profileFile = $conn->query("SELECT file_path FROM student_files WHERE student_id='$national_id' AND file_type='profile_image' LIMIT 1")->fetch_assoc();
$profileImagePath = !empty($profileFile['file_path']) ? $profileFile['file_path'] : (!empty($student['image_path']) ? htmlspecialchars($student['image_path']) : 'uploads/default-avatar.jpg');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>الملف الشخصي - <?php echo htmlspecialchars($center['name']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body::-webkit-scrollbar { display: none; }
        body { -ms-overflow-style: none; scrollbar-width: none; font-family: 'Tajawal', sans-serif; }
    </style>
</head>

<body class="min-h-screen flex flex-col bg-gray-800 text-white relative">

<div class="absolute inset-0">
    <img src="../image/kitchen.png" class="w-full h-full object-cover filter brightness-75" alt="خلفية مطبخ">
</div>
<div class="absolute inset-0 bg-black/40"></div>

<div class="relative flex flex-col min-h-screen z-10">

    <header class="bg-gray-800/70 backdrop-blur-md shadow-md border-b border-yellow-400/30 p-4 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto flex justify-between items-center relative">
            
            <a href="student_dashboard.php" class="flex items-center gap-3 bg-gray-800/50 py-2 px-4 rounded-2xl border border-gray-700/50 hover:bg-gray-700/50 hover:border-yellow-500/50 transition-all duration-300 group">
                <div class="h-8 w-8 bg-yellow-500/20 rounded-full flex items-center justify-center text-xl border border-yellow-500/30 group-hover:scale-110 transition-transform">👨‍🍳</div>
                <div class="text-right">
                     
                    <p class="text-sm font-black text-yellow-300 leading-none group-hover:text-yellow-400"><?php echo htmlspecialchars($student['full_name'] ?? 'الطالب'); ?></p>
                </div>
            </a>

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
            <img id="studentImage" src="<?php echo $profileImagePath; ?>" 
                 class="w-36 h-36 rounded-full border-4 border-yellow-400 shadow-lg object-cover transition-transform duration-300 group-hover:scale-105 cursor-pointer"
                 alt="صورة الطالب">
            <div id="uploadIcon" class="absolute bottom-0 right-0 bg-yellow-400 text-gray-900 rounded-full w-10 h-10 flex items-center justify-center text-2xl cursor-pointer hover:bg-yellow-500 transition">+</div>
            <input type="file" id="profileInput" class="hidden" accept="image/*">
        </div>

        <div class="flex gap-10 mt-10">
            <button id="changePassBtn"
                    class="flex flex-col items-center justify-center bg-gray-800/70 hover:bg-yellow-400 hover:text-gray-900 w-40 h-40 rounded-2xl shadow-xl transition-all duration-300 backdrop-blur-md">
                <span class="text-5xl">🔒</span>
                <span class="text-sm font-semibold mt-1">تغيير كلمة السر</span>
            </button>

            <button id="infoBtn"
                    class="flex flex-col items-center justify-center bg-gray-800/70 hover:bg-yellow-400 hover:text-gray-900 w-40 h-40 rounded-2xl shadow-xl transition-all duration-300 backdrop-blur-md">
                <span class="text-5xl">👤</span>
                <span class="text-sm font-semibold mt-1">البيانات</span>
            </button>
            <button id="myAdsBtn"
                    class="flex flex-col items-center justify-center bg-gray-800/70 hover:bg-yellow-400 hover:text-gray-900 w-40 h-40 rounded-2xl shadow-xl transition-all duration-300 backdrop-blur-md">
                <span class="text-5xl">📢</span>
                <span class="text-sm font-semibold mt-1">إعلاناتي</span>
            </button>
        </div>
    </main>

    <footer class="relative z-10 bg-gray-800/80 text-yellow-300 py-4 mt-6 border-t border-yellow-400/20">
        <div class="max-w-6xl mx-auto grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 text-xs px-4">
            <div>
                <h3 class="text-base font-semibold mb-1">📞 معلومات التواصل</h3>
                <p>الهاتف: <?php echo htmlspecialchars($center['phone']); ?></p>
                <p>الجوال: <?php echo htmlspecialchars($center['mobile']); ?></p>
                <p>واتساب: 
                    <a href="https://wa.me/<?php echo htmlspecialchars($center['whatsapp']); ?>" class="text-green-400 hover:underline" target="_blank">
                        <?php echo htmlspecialchars($center['whatsapp']); ?>
                    </a>
                </p>
                <p>الإيميل:
                    <a href="mailto:<?php echo htmlspecialchars($center['email']); ?>" class="text-blue-300 hover:underline">
                        <?php echo htmlspecialchars($center['email']); ?>
                    </a>
                </p>
            </div>
            <div>
                <h3 class="text-base font-semibold mb-1">🌐 السوشيال ميديا</h3>
                <a href="<?php echo htmlspecialchars($center['facebook']); ?>" class="block hover:underline" target="_blank">Facebook</a>
                <a href="<?php echo htmlspecialchars($center['instagram']); ?>" class="block hover:underline" target="_blank">Instagram</a>
            </div>
            <div>
                <h3 class="text-base font-semibold mb-1">📍 الموقع</h3>
                <a href="https://www.google.com/maps?q=<?php echo htmlspecialchars($center['latitude']); ?>,<?php echo htmlspecialchars($center['longitude']); ?>" 
                   target="_blank"
                   class="inline-block mt-1 px-3 py-1 bg-yellow-400 text-gray-900 font-bold rounded hover:bg-yellow-500 transition">
                    افتتح الخريطة
                </a>
            </div>
        </div>
        <div class="text-center mt-2 text-gray-400 text-xs">
            © <?php echo date('Y'); ?> جميع الحقوق محفوظة - <?php echo htmlspecialchars($center['name']); ?>
        </div>
    </footer>
</div>

<div id="passModal" class="hidden fixed inset-0 bg-black/60 flex items-center justify-center z-50">
    <div class="bg-gray-900 border border-yellow-400/30 rounded-2xl shadow-2xl p-6 w-full max-w-sm relative text-right">
        <h2 class="text-lg font-bold text-yellow-400 mb-4 text-center">تغيير كلمة المرور</h2>
        <form id="passForm" class="space-y-3">
            <input type="password" id="currentPass" placeholder="كلمة المرور الحالية" class="w-full p-2 rounded-md bg-gray-800 border border-gray-700 focus:ring-2 focus:ring-yellow-400 outline-none" required>
            <input type="password" id="newPass" placeholder="كلمة المرور الجديدة" class="w-full p-2 rounded-md bg-gray-800 border border-gray-700 focus:ring-2 focus:ring-yellow-400 outline-none" required>
            <input type="password" id="confirmPass" placeholder="تأكيد كلمة المرور" class="w-full p-2 rounded-md bg-gray-800 border border-gray-700 focus:ring-2 focus:ring-yellow-400 outline-none" required>
            <p id="errorMsg" class="text-red-400 text-sm text-center"></p>
            <button type="submit" class="w-full bg-yellow-400 text-gray-900 font-bold py-2 rounded-lg hover:bg-yellow-500 transition">حفظ</button>
            <button type="button" id="closeModal" class="w-full mt-2 bg-gray-700 text-gray-200 py-2 rounded-lg hover:bg-gray-600 transition">إلغاء</button>
        </form>
    </div>
</div>

<script>
// رفع صورة شخصية
const uploadIcon = document.getElementById('uploadIcon');
const profileInput = document.getElementById('profileInput');

uploadIcon.onclick = () => profileInput.click();

profileInput.onchange = () => {
    const file = profileInput.files[0];
    if (!file) return;
    if (file.size > 2097152) { 
        alert('حجم الملف كبير جداً (الحد الأقصى 2 ميجابايت).');
        return;
    }
    const formData = new FormData();
    formData.append('action','upload_profile');
    formData.append('profile_image', file);
    const reader = new FileReader();
    reader.onload = (e) => { document.getElementById('studentImage').src = e.target.result; };
    reader.readAsDataURL(file);

    fetch('', {method:'POST', body: formData})
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            document.getElementById('studentImage').src = data.file_path + '?' + new Date().getTime(); 
            alert('✅ تم رفع الصورة بنجاح!');
        } else {
            alert('❌ فشل الرفع: ' + data.message);
        }
    })
    .catch(err => alert('حدث خطأ أثناء الاتصال بالخادم لرفع الصورة.'));
};

// الأزرار والتفاعلات
const modal = document.getElementById('passModal');
const openBtn = document.getElementById('changePassBtn');
const closeBtn = document.getElementById('closeModal');
const infoBtn = document.getElementById('infoBtn');
const myAdsBtn = document.getElementById('myAdsBtn');
const errorMsg = document.getElementById('errorMsg');

openBtn.onclick = () => modal.classList.remove('hidden');
closeBtn.onclick = () => modal.classList.add('hidden');
infoBtn.onclick = () => window.location.href = 'complete_profile.php';
myAdsBtn.onclick = () => window.location.href = 'student_posts.php';

modal.addEventListener('click', (e) => { if (e.target.id === 'passModal') modal.classList.add('hidden'); });

document.getElementById('passForm').addEventListener('submit', e => {
    e.preventDefault();
    const cur = document.getElementById('currentPass').value.trim();
    const newP = document.getElementById('newPass').value.trim();
    const conf = document.getElementById('confirmPass').value.trim();
    errorMsg.textContent = '';
    if (newP.length < 6) return errorMsg.textContent = 'كلمة المرور قصيرة جداً (الحد الأدنى 6 أحرف).';
    if (newP !== conf) return errorMsg.textContent = 'كلمتا المرور غير متطابقتين.';
    fetch('', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams({ action: 'change_password', current: cur, newPass: newP })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('✅ تم تغيير كلمة المرور بنجاح!');
            modal.classList.add('hidden');
            document.getElementById('passForm').reset();
        } else {
            errorMsg.textContent = data.message;
        }
    });
});
</script>
</body>
</html>