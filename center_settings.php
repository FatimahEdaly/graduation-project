<?php
session_start();
include "config.php";

// جلب بيانات أول مركز
$center = $conn->query("SELECT * FROM centers LIMIT 1")->fetch_assoc();
$center_name = $center['name'];

// مجلدات الرفع
$imageDir = "uploads/centers/images/";
$fileDir  = "uploads/centers/files/";

// تأكد أن المجلدات موجودة
if (!is_dir($imageDir)) mkdir($imageDir, 0755, true);
if (!is_dir($fileDir)) mkdir($fileDir, 0755, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name       = $_POST['name'];
    $phone      = $_POST['phone'];
    $mobile     = $_POST['mobile'];
    $whatsapp   = $_POST['whatsapp'];
    $facebook   = $_POST['facebook'];
    $instagram  = $_POST['instagram'];
    $email      = $_POST['email'];
    $latitude   = $_POST['latitude'];
    $longitude  = $_POST['longitude'];

    // رفع صورة الشعار
    if (!empty($_FILES['image_path']['name'])) {
        $ext = pathinfo($_FILES['image_path']['name'], PATHINFO_EXTENSION);
        $imgName = "logo_" . time() . "." . $ext;
        $imgPath = $imageDir . $imgName;
        move_uploaded_file($_FILES['image_path']['tmp_name'], $imgPath);
    } else {
        $imgPath = $center['image_path'];
    }

    // رفع الملف التعريفي (PDF)
    if (!empty($_FILES['profile_file_path']['name'])) {
        $ext = pathinfo($_FILES['profile_file_path']['name'], PATHINFO_EXTENSION);
        $fileName = "profile_" . time() . "." . $ext;
        $filePath = $fileDir . $fileName;
        move_uploaded_file($_FILES['profile_file_path']['tmp_name'], $filePath);
    } else {
        $filePath = $center['profile_file_path'];
    }

    // تحديث قاعدة البيانات
    $stmt = $conn->prepare("UPDATE centers SET name=?, phone=?, mobile=?, whatsapp=?, facebook=?, instagram=?, email=?, image_path=?, profile_file_path=?, latitude=?, longitude=? WHERE id=?");
    $stmt->bind_param("sssssssssdsi", $name, $phone, $mobile, $whatsapp, $facebook, $instagram, $email, $imgPath, $filePath, $latitude, $longitude, $center['id']);
    $stmt->execute();

    header("Location: center_settings.php?updated=true");
    exit();
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إعدادات مركز <?= $center_name; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700;900&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Tajawal', sans-serif;
            background: radial-gradient(circle at top left, #1f2937 0%, #111827 100%);
            min-height: 100vh;
        }

        /* ستايل الفورم - تأثير زجاجي غامق ليتناسب مع الخلفية */
        .glass-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        input, select {
            background: rgba(255, 255, 255, 0.05) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            color: white !important;
        }

        input:focus {
            border-color: #fbbf24 !important;
            ring: 2px #fbbf24;
        }

        /* رسالة النجاح */
        #success-msg {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 100;
            padding: 12px 25px;
            border-radius: 12px;
            background-color: #fbbf24;
            color: #000;
            font-weight: bold;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.5);
            opacity: 0;
            transition: opacity 0.5s;
        }

        /* نافذة تكبير الصورة */
        #logo-modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.8);
            z-index: 100;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(5px);
        }
    </style>
</head>
<body class="text-white">

<?php if (isset($_GET['updated'])): ?>
<div id="success-msg">✅ تم حفظ البيانات بنجاح</div>
<script>
    const msg = document.getElementById('success-msg');
    msg.style.opacity = 1;
    setTimeout(() => { msg.style.opacity = 0; }, 5000);
</script>
<?php endif; ?>

<div class="min-h-screen flex flex-col items-center justify-center p-6">
    <form method="POST" enctype="multipart/form-data" class="glass-card p-8 rounded-[2rem] w-full max-w-4xl shadow-2xl">
        <h2 class="text-3xl font-black text-center mb-8 text-yellow-500 italic uppercase tracking-wider">
            ⚙️ إعدادات  <?= $center_name; ?>
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block mb-2 text-sm text-gray-400">📛 اسم المركز</label>
                <input value="<?= $center['name']; ?>" name="name" class="w-full p-3 rounded-xl outline-none transition-all">
            </div>
            <div>
                <label class="block mb-2 text-sm text-gray-400">☎ الهاتف</label>
                <input value="<?= $center['phone']; ?>" name="phone" class="w-full p-3 rounded-xl outline-none transition-all">
            </div>
            <div>
                <label class="block mb-2 text-sm text-gray-400">📱 الجوال</label>
                <input value="<?= $center['mobile']; ?>" name="mobile" class="w-full p-3 rounded-xl outline-none transition-all">
            </div>
            <div>
                <label class="block mb-2 text-sm text-gray-400">💬 واتساب</label>
                <input value="<?= $center['whatsapp']; ?>" name="whatsapp" class="w-full p-3 rounded-xl outline-none transition-all">
            </div>
            <div>
                <label class="block mb-2 text-sm text-gray-400">📘 فيسبوك</label>
                <input value="<?= $center['facebook']; ?>" name="facebook" class="w-full p-3 rounded-xl outline-none transition-all">
            </div>
            <div>
                <label class="block mb-2 text-sm text-gray-400">📸 إنستغرام</label>
                <input value="<?= $center['instagram']; ?>" name="instagram" class="w-full p-3 rounded-xl outline-none transition-all">
            </div>
            <div>
                <label class="block mb-2 text-sm text-gray-400">📧 الإيميل</label>
                <input value="<?= $center['email']; ?>" name="email" class="w-full p-3 rounded-xl outline-none transition-all">
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block mb-2 text-sm text-gray-400">📍 Latitude</label>
                    <input value="<?= $center['latitude']; ?>" name="latitude" class="w-full p-3 rounded-xl outline-none transition-all">
                </div>
                <div>
                    <label class="block mb-2 text-sm text-gray-400">📍 Longitude</label>
                    <input value="<?= $center['longitude']; ?>" name="longitude" class="w-full p-3 rounded-xl outline-none transition-all">
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-8">
            <div class="bg-white/5 p-4 rounded-2xl border border-white/10">
                <label class="block font-bold mb-3 text-yellow-500 text-sm">🖼 شعار المركز الحالي</label>
                <div class="flex items-center gap-4">
                    <?php if ($center['image_path']): ?>
                        <img id="logo-thumb" src="<?= $center['image_path']; ?>" class="w-20 h-20 object-cover rounded-full border-2 border-yellow-500 cursor-pointer hover:scale-105 transition-transform" alt="Logo">
                    <?php endif; ?>
                    <input type="file" name="image_path" class="text-xs text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-yellow-500 file:text-black hover:file:bg-yellow-400">
                </div>
            </div>

            <div class="bg-white/5 p-4 rounded-2xl border border-white/10">
                <label class="block font-bold mb-3 text-yellow-500 text-sm">📎 ملف تعريف (PDF)</label>
                <div class="flex flex-col gap-2">
                    <?php if ($center['profile_file_path']): ?>
                        <a href="<?= $center['profile_file_path']; ?>" class="text-blue-400 text-xs hover:underline" target="_blank">📄 استعراض الملف الحالي</a>
                    <?php endif; ?>
                    <input type="file" name="profile_file_path" class="text-xs text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-yellow-500 file:text-black hover:file:bg-yellow-400">
                </div>
            </div>
        </div>

        <button type="submit" class="mt-10 w-full py-4 bg-yellow-500 text-black font-black rounded-2xl hover:bg-yellow-400 shadow-lg shadow-yellow-500/20 transition-all active:scale-95 text-lg">
            💾 حفظ كافة التغييرات
        </button>
    </form>
    
    <footer class="mt-8 text-gray-500 text-[10px] uppercase tracking-widest">
        © <?= date('Y'); ?> جميع الحقوق محفوظة - <?= $center_name; ?>
    </footer>
</div>

<div id="logo-modal">
    <span id="logo-close" class="absolute top-5 right-8 text-5xl text-white cursor-pointer hover:text-yellow-500">&times;</span>
    <img src="<?= $center['image_path']; ?>" class="max-w-[80%] max-h-[80%] rounded-xl shadow-2xl border-2 border-white/20" alt="Logo Zoom">
</div>

<script>
    const thumb = document.getElementById('logo-thumb');
    const modal = document.getElementById('logo-modal');
    const closeBtn = document.getElementById('logo-close');

    if(thumb) {
        thumb.addEventListener('click', () => { modal.style.display = "flex"; });
    }
    closeBtn.addEventListener('click', () => { modal.style.display = "none"; });
    modal.addEventListener('click', e => { if(e.target === modal) modal.style.display = "none"; });
</script>

</body>
</html>