<?php
session_start();
require_once 'config.php';
$center = $conn->query("SELECT * FROM centers LIMIT 1")->fetch_assoc();

$reset_error = $_SESSION['reset_error'] ?? null;
if ($reset_error) {
    $open_reset_modal = true; 
    unset($_SESSION['reset_error']); 
} else {
    $open_reset_modal = false;
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول - <?php echo $center['name']; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700;900&display=swap" rel="stylesheet">
    <style>
        body { 
            font-family: 'Tajawal', sans-serif; 
            background: radial-gradient(circle at top left, #1f2937 0%, #111827 100%);
            overflow-x: hidden;
        }
        .glass-effect {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
    </style>
</head>

<body class="min-h-screen flex flex-col text-white relative">

    <div class="w-full bg-gray-900/80 backdrop-blur-md text-yellow-500 border-b border-white/10 h-20 flex items-center justify-between px-8 relative z-30">
        <div class="flex items-center gap-4">
            <img src="<?php echo htmlspecialchars($center['image_path']); ?>" class="h-14 rounded-full border-2 border-yellow-500 shadow-lg" alt="Logo">
            <h1 class="text-2xl font-black text-yellow-500 tracking-tighter uppercase">CHEF-LINK</h1>
        </div>
        
        <div class="flex gap-4 items-center">
            <a href="HomePage.php" 
               class="bg-white/5 border border-white/10 px-5 py-2.5 rounded-xl text-sm font-bold text-white hover:border-yellow-500/50 hover:text-yellow-400 transition-all duration-300 backdrop-blur-md shadow-lg">
                الصفحه الرئيسية
            </a>
        </div>
    </div>

    <main class="relative z-10 flex-grow flex items-center justify-center px-4 py-12">
        <div class="max-w-7xl w-full mx-auto grid grid-cols-1 lg:grid-cols-2 gap-40 items-center">
            
            <div class="order-1 hidden lg:flex justify-center lg:justify-end items-end relative h-[450px]">
                <div class="absolute w-64 h-64 bg-yellow-500/5 blur-[100px] rounded-full bottom-20"></div>
                <div class="relative flex items-end">
                    <img src="images/male.png" alt="Chef Boy" 
                         class="h-[300px] lg:h-[300px] w-auto object-contain relative z-0 drop-shadow-[0_20px_50px_rgba(0,0,0,0.8)]">
                    
                    <img src="images/famel.png" alt="Chef Girl" 
                         class="h-[300px] lg:h-[300px] w-auto object-contain relative z-10 -ms-12 lg:-ms-16 transform translate-y-6 drop-shadow-[0_20px_50px_rgba(0,0,0,0.8)]">
                </div>
            </div>

            <div class="order-2 flex flex-col items-center lg:items-start">
                <div class="glass-effect p-8 rounded-[2rem] shadow-2xl w-full max-w-md border-t border-white/20">
                    <h2 class="text-2xl font-bold text-center mb-8 text-yellow-500">تسجيل الدخول</h2>
                    
                    <?php if (isset($_SESSION['login_error'])): ?>
                        <div class="bg-red-500/20 border border-red-500 text-red-200 text-sm text-center py-2 rounded-xl mb-4 italic">
                            <?php echo $_SESSION['login_error']; unset($_SESSION['login_error']); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="check_login.php" class="space-y-6">
                        <div>
                            <label class="block mb-2 text-sm text-gray-400">رقم المستخدم</label>
                            <input type="text" name="user_id" required class="w-full p-4 bg-gray-800/50 rounded-2xl border border-gray-700 text-white focus:ring-2 focus:ring-yellow-500 outline-none transition-all">
                        </div>
                        <div>
                            <label class="block mb-2 text-sm text-gray-400">كلمة المرور</label>
                            <input type="password" name="password" required class="w-full p-4 bg-gray-800/50 rounded-2xl border border-gray-700 text-white focus:ring-2 focus:ring-yellow-500 outline-none transition-all">
                        </div>
                        <button type="submit" class="w-full py-4 bg-yellow-500 text-black font-black rounded-2xl hover:bg-yellow-400 shadow-lg shadow-yellow-500/20 transition-all active:scale-95">دخول للنظام</button>
                    </form>
                    
                    <div class="text-center mt-6 text-xs">
                        <a href="#" id="openResetModal" class="text-gray-500 hover:text-yellow-500 transition-colors">نسيت كلمة المرور؟</a>
                    </div>
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

    <div id="resetModal" class="fixed inset-0 bg-black/70 hidden items-center justify-center z-50 p-4 backdrop-blur-sm">
        <div class="bg-[#1a222f] rounded-[2rem] shadow-2xl w-full max-w-md p-8 relative border border-white/10 text-white">
            <button id="closeResetModal" class="absolute top-4 left-4 text-gray-500 hover:text-white text-3xl">×</button>
            <h2 class="text-2xl font-bold mb-6 text-center italic text-yellow-500">استعادة كلمة المرور</h2>
            <form id="resetForm" method="POST" action="start_reset_password.php" class="space-y-4">
                <input type="text" name="national_id" placeholder="رقم الهوية" required class="w-full p-4 bg-gray-800 rounded-xl border border-gray-700 text-white focus:ring-2 focus:ring-yellow-500 outline-none">
                <input type="email" name="email" placeholder="الإيميل" required class="w-full p-4 bg-gray-800 rounded-xl border border-gray-700 text-white focus:ring-2 focus:ring-yellow-500 outline-none">
                <button type="submit" class="w-full py-4 bg-yellow-500 text-black font-black rounded-xl hover:bg-yellow-400 transition-all shadow-lg">متابعة</button>
            </form>
        </div>
    </div>

    <script>
        const openReset = document.getElementById("openResetModal");
        const closeReset = document.getElementById("closeResetModal");
        const resetModal = document.getElementById("resetModal");

        <?php if ($open_reset_modal): ?>
        document.addEventListener("DOMContentLoaded", () => {
            resetModal.classList.remove("hidden");
            resetModal.classList.add("flex");
        });
        <?php endif; ?>

        openReset.addEventListener("click", (e) => { e.preventDefault(); resetModal.classList.remove("hidden"); resetModal.classList.add("flex"); });
        closeReset.addEventListener("click", () => { resetModal.classList.add("hidden"); resetModal.classList.remove("flex"); });
        resetModal.addEventListener("click", (e) => { if (e.target === resetModal) { resetModal.classList.add("hidden"); resetModal.classList.remove("flex"); } });
    </script>
</body>
</html>