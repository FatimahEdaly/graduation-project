<?php
session_start();
require_once 'config.php';

// ==========================================================
// 🌟 منطق معالجة طلب التسجيل (PHP) 🌟
// ==========================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_request') {
    $name = trim($_POST['establishment_name'] ?? '');
    $type = trim($_POST['establishment_type'] ?? '');
    if ($type === 'آخر' && !empty(trim($_POST['type_custom'] ?? ''))) {
        $type = trim($_POST['type_custom']);
    }
    
    $location = trim($_POST['location'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone_number = trim($_POST['phone_number'] ?? ''); 
    $social_link = trim($_POST['social_link'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if (empty($name) || empty($type) || empty($location) || empty($email) || empty($phone_number)) {
        $_SESSION['request_message'] = 'خطأ: جميع الحقول المطلوبة يجب ملؤها.';
        $_SESSION['request_message_type'] = 'error';
    } else {
        $sql = "INSERT INTO registration_requests 
        (establishment_name, establishment_type, location, email, phone_number, social_link, notes, is_approved)
        VALUES (?, ?, ?, ?, ?, ?, ?, FALSE)";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssss", $name, $type, $location, $email, $phone_number, $social_link, $notes);

        if ($stmt->execute()) {
            $_SESSION['request_message'] = "✅ تم استلام طلبكم بنجاح. سيتم مراجعته قريباً.";
            $_SESSION['request_message_type'] = 'success';
        } else {
            $_SESSION['request_message'] = 'خطأ في قاعدة البيانات: ' . $conn->error;
            $_SESSION['request_message_type'] = 'error';
        }
    }
    header("Location: HomePage.php");
    exit;
}

$center = $conn->query("SELECT * FROM centers LIMIT 1")->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $center['name']; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700;900&display=swap" rel="stylesheet">

    <style>
        body {
            margin: 0;
            font-family: 'Tajawal', sans-serif;
            background: radial-gradient(circle at top left, #1f2937 0%, #111827 100%);
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* SLIDER CSS */
        .slider {
            width: 520px;
            height: 300px;
            border-radius: 20px;
            margin-right: 0px;
            margin-top: 100px;
            overflow: hidden;
            position: relative;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .slide {
            display: none;
            padding: 40px;
            text-align: right;
        }

        .slide p:first-child {
            color: #fbbf24; 
            font-size: 26px;
            font-weight: 900;
            margin-bottom: 15px;
        }

        .slide p:last-child {
            color: #d1d5db;
            font-size: 18px;
            line-height: 1.6;
        }

        .slide.active { display: block; }

        .navigation_manual {
            display: flex;
            justify-content: center;
            margin-top: -40px;
            position: relative;
            z-index: 20;
            width: 520px;
            margin-left: 60px;
        }

        .manual_btn {
            border: 2px solid #fbbf24;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            cursor: pointer;
            transition: .3s;
            margin-top:  10px;
            margin-right:5px;
            background: transparent;
        }

        .manual_btn:hover, .manual_btn.active_btn {
            background: #fbbf24;
        }
    </style>
</head>

<body class="text-white">

<div class="w-full bg-gray-900/80 backdrop-blur-md text-yellow-500 border-b border-white/10 h-20 flex items-center justify-between px-8 relative z-30">
    <div class="flex items-center gap-4">
        <img src="<?php echo htmlspecialchars($center['image_path']); ?>" class="h-14 rounded-full border-2 border-yellow-500 shadow-lg" alt="Logo">
        <h1 class="text-2xl font-black text-yellow-500 tracking-tighter uppercase">CHEF-LINK</h1>
    </div>
    
    <div class="flex gap-4 items-center">
        <button id="openRequestModal" 
                class="bg-white/5 border border-white/10 px-5 py-2.5 rounded-xl text-sm font-bold text-yellow-500 hover:bg-yellow-500 hover:text-black transition-all duration-300 backdrop-blur-md shadow-lg">
            طلب تسجيل مؤسسة (مجاني)
        </button>

        <a href="HTMLPage2.html" 
           class="bg-white/5 border border-white/10 px-5 py-2.5 rounded-xl text-sm font-bold text-white hover:border-yellow-500/50 hover:text-yellow-400 transition-all duration-300 backdrop-blur-md shadow-lg">
            من نحن
        </a>

        <a href="login.php" 
           class="bg-white/5 border border-white/10 px-5 py-2.5 rounded-xl text-sm font-bold text-white hover:border-yellow-500/50 hover:text-yellow-400 transition-all duration-300 backdrop-blur-md shadow-lg">
            تسجيل الدخول
        </a>
    </div>
</div>

<main class="relative z-10 pb-20 px-8"> 
    <?php if (isset($_SESSION['request_message'])): ?>
        <div class="max-w-xl mx-auto mb-6 p-4 rounded-xl text-center shadow-2xl <?php echo $_SESSION['request_message_type'] === 'success' ? 'bg-green-500/20 border border-green-500 text-green-200' : 'bg-red-500/20 border border-red-500 text-red-200'; ?>">
            <?php echo htmlspecialchars($_SESSION['request_message']); unset($_SESSION['request_message']); ?>
        </div>
    <?php endif; ?>

    <div class="max-w-8xl mx-auto grid grid-cols-1 lg:grid-cols-2 gap-40 items-end mt-10">
        
        <div class="order-1 flex justify-center lg:justify-end items-end relative h-[450px]">
            <div class="absolute w-64 h-64 bg-yellow-500/10 blur-[100px] rounded-full bottom-20"></div>
            
            <div class="relative flex items-end">
                <img src="images/male.png" alt="Chef Boy" 
                     class="h-[350px] lg:h-[300px] w-auto object-contain relative z-0 drop-shadow-[0_20px_50px_rgba(0,0,0,0.8)]">
                
                <img src="images/famel.png" alt="Chef Girl" 
                     class="h-[300px] lg:h-[300px] w-auto object-contain relative z-10 -ms-24 lg:-ms-30 transform translate-y-6 drop-shadow-[0_20px_50px_rgba(0,0,0,0.8)]">
            </div>
        </div>

        <div class="order-2 flex flex-col items-center lg:items-start">
            <div class="slider !m-0 shadow-2xl border border-white/5"> 
                <div class="slide active">
                    <p>جسر بين خريجي الطهي وفرص العمل</p>
                    <p>نعمل على ربط المبدعين في عالم الطهي بأرقى المطاعم والفنادق العالمية لتطوير مستقبلهم المهني.</p>
                </div>
                <div class="slide">
                    <p>اكتشف الفرص المناسبة لك</p>
                    <p>منصة متكاملة تتيح لك الوصول لأهم الوظائف المتاحة في السوق بناءً على مهاراتك وخبراتك.</p>
                </div>
                <div class="slide">
                    <p>قاعدة بيانات مركز مريم هاشم</p>
                    <p>نفتخر بتقديم نخبة من الخريجين المتخصصين في فنون الطهي الحديثة والتقليدية.</p>
                </div>
                <div class="slide">
                    <p>سهولة البحث والتوظيف</p>
                    <p>يمكن لأصحاب العمل تصفح السير الذاتية واختيار الكفاءات التي تناسب معاييرهم العالية.</p>
                </div>
            </div>
            
            <div class="navigation_manual !m-0 mt-4 flex justify-center w-[520px]">
                <button class="manual_btn active_btn" onclick="showSlide(0)"></button>
                <button class="manual_btn" onclick="showSlide(1)"></button>
                <button class="manual_btn" onclick="showSlide(2)"></button>
                <button class="manual_btn" onclick="showSlide(3)"></button>
            </div>
        </div>

    </div>
</main>

<footer class="relative z-10 bg-gray-800 text-yellow-300 py-4 mt-6 border-t border-yellow-400/20">
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
                    افتح الخريطة
                </a>
            </div>
        </div>

        <div class="text-center mt-2 text-gray-400 text-xs">
            © <?php echo date('Y'); ?> جميع الحقوق محفوظة - <?php echo htmlspecialchars($center['name']); ?>
        </div>
    </footer>

<div id="requestModal" class="hidden fixed inset-0 bg-black/80 backdrop-blur-sm flex items-start justify-center z-50 p-4 overflow-y-auto">
    <div class="bg-gray-900 border border-white/10 rounded-[2rem] shadow-2xl p-8 w-full max-w-lg relative text-white mt-10 mb-10">
        <h2 class="text-2xl font-black text-yellow-500 mb-4 text-center italic">تقديم طلب تسجيل مؤسسة</h2>
        <form id="requestForm" action="HomePage.php" method="POST" class="space-y-4">
            <input type="hidden" name="action" value="submit_request">
            <input type="text" name="establishment_name" required placeholder="اسم المؤسسة" class="w-full p-4 rounded-xl bg-gray-800 border border-gray-700 outline-none focus:border-yellow-500 transition">
            <select name="establishment_type" id="establishment_type" required class="w-full p-4 rounded-xl bg-gray-800 border border-gray-700 outline-none focus:border-yellow-500 transition">
                <option value="">نوع المؤسسة</option>
                <option value="مطعم">مطعم</option>
                <option value="فندق">فندق</option>
                <option value="آخر">آخر</option>
            </select>
            <div id="custom_type_field" class="hidden">
                <input type="text" name="type_custom" id="type_custom" placeholder="حدد النوع" class="w-full p-4 rounded-xl bg-gray-800 border border-gray-700 outline-none focus:border-yellow-500 transition">
            </div>
            <input type="text" name="location" required placeholder="الموقع / العنوان" class="w-full p-4 rounded-xl bg-gray-800 border border-gray-700 outline-none focus:border-yellow-500 transition">
            <input type="email" name="email" required placeholder="البريد الإلكتروني" class="w-full p-4 rounded-xl bg-gray-800 border border-gray-700 outline-none focus:border-yellow-500 transition">
            <input type="tel" name="phone_number" required placeholder="رقم الهاتف" class="w-full p-4 rounded-xl bg-gray-800 border border-gray-700 outline-none focus:border-yellow-500 transition">
            <textarea name="notes" rows="2" placeholder="ملاحظات (اختياري)" class="w-full p-4 rounded-xl bg-gray-800 border border-gray-700 outline-none focus:border-yellow-500 transition"></textarea>
            <button type="submit" class="w-full bg-yellow-500 text-black font-black py-4 rounded-xl hover:bg-yellow-400 transition shadow-lg shadow-yellow-500/20">إرسال الطلب</button>
            <button type="button" id="closeRequestModal" class="w-full text-gray-500 hover:text-white transition text-sm">إلغاء</button>
        </form>
    </div>
</div>

<script>
    // Slider Script
    const slides = document.querySelectorAll('.slide');
    const btns = document.querySelectorAll('.manual_btn');
    let current = 0;

    function showSlide(index){
        slides.forEach(slide => slide.classList.remove('active'));
        btns.forEach(btn => btn.classList.remove('active_btn'));
        slides[index].classList.add('active');
        btns[index].classList.add('active_btn');
        current = index;
    }

    setInterval(() => {
        current = (current + 1) % slides.length;
        showSlide(current);
    }, 6000);

    // Modal Logic
    $('#openRequestModal').click(function() { $('#requestModal').removeClass('hidden'); });
    $('#closeRequestModal').click(function() { $('#requestModal').addClass('hidden'); });
    $('#establishment_type').change(function() {
        if ($(this).val() === 'آخر') { $('#custom_type_field').removeClass('hidden'); }
        else { $('#custom_type_field').addClass('hidden'); }
    });
</script>

</body>
</html>