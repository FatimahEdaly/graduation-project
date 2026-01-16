<?php
session_start();
require_once 'config.php';

//  التحقق من الصلاحيات
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'establishment') {
    header("Location: login.php");
    exit;
}

if (!isset($_GET['post_id'])) {
    die("خطأ: لم يتم تحديد المنشور.");
}

$post_id = intval($_GET['post_id']);
$establishment_id = $_SESSION['user_id'];
$conn->set_charset("utf8mb4");


$est_query = $conn->prepare("SELECT full_name, profile_image FROM registered_establishments WHERE id = ?");
$est_query->bind_param("i", $establishment_id);
$est_query->execute();
$establishment = $est_query->get_result()->fetch_assoc();


$checkPost = $conn->prepare("SELECT content FROM social_posts WHERE id = ? AND establishment_id = ?");
$checkPost->bind_param("ii", $post_id, $establishment_id);
$checkPost->execute();
$post_res = $checkPost->get_result()->fetch_assoc();

if (!$post_res) {
    die("غير مسموح لك بالوصول لهذا المنشور.");
}
$post_text = $post_res['content'];

// جلب الطلاب المهتمين مع برامجهم التدريبية
$sql = "SELECT s.*, spi.id as interest_id,
        (SELECT GROUP_CONCAT(CONCAT(tp.program_name, ' [', tp.program_type, ']') SEPARATOR '||') 
         FROM student_programs stp 
         JOIN training_programs tp ON stp.program_id = tp.id 
         WHERE stp.student_id = s.national_id) as enrolled_programs
        FROM students s
        INNER JOIN student_post_interested spi ON s.national_id = spi.graduate_id
        WHERE spi.post_id = ?
        ORDER BY spi.id DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $post_id);
$stmt->execute();
$interested_students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>المهتمين | شيف لينك</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Tajawal', sans-serif; background-color: #0f172a; }
        .student-card { background: rgba(30, 41, 59, 0.5); border: 1px solid rgba(255,255,255,0.05); backdrop-filter: blur(10px); }
        .custom-scroll::-webkit-scrollbar { width: 3px; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #eab308; border-radius: 10px; }
        details summary { list-style: none; cursor: pointer; }
        details summary::-webkit-details-marker { display: none; }
        .bottom-left-chef { position: fixed; bottom: 0; left: 0; z-index: 100; width: 230px; pointer-events: none; }
        
    </style>
</head>
<body class="text-white">
<div class="bottom-left-chef">
    <img src="images/wait.png" alt="Chef Character" class="w-full h-auto drop-shadow-2xl">
</div>
<header class="bg-slate-900/80 backdrop-blur-lg border-b border-white/10 p-4 sticky top-0 z-50">
    <div class="max-w-6xl mx-auto flex justify-between items-center relative">
        <a href="establishment_dashboard.php" class="flex items-center gap-3 bg-white/5 py-1.5 px-4 rounded-2xl border border-white/10 hover:border-yellow-500/50 transition-all">
            <?php if (!empty($establishment['profile_image'])): ?>
                <img src="uploads/<?= htmlspecialchars($establishment['profile_image']) ?>" class="h-8 w-8 rounded-full object-cover border border-yellow-500/30" alt="Logo">
            <?php else: ?>
                <div class="h-8 w-8 bg-yellow-500/20 rounded-full flex items-center justify-center text-xl border border-yellow-500/30">🏢</div>
            <?php endif; ?>
            <div class="hidden sm:block">
                <p class="text-[10px] text-gray-400 font-bold leading-none mb-1 uppercase tracking-wider"></p>
                <p class="text-sm font-black text-white leading-none"><?= htmlspecialchars($establishment['full_name'] ?? 'المنشأة'); ?></p>
            </div>
        </a>

        <div class="absolute left-1/2 -translate-x-1/2">
            <h1 class="text-2xl font-black text-yellow-500 italic tracking-tighter uppercase">CHEF-LINK</h1>
        </div>

        <a href="establishment_posts.php" class="bg-slate-800 hover:bg-yellow-500 hover:text-black px-5 py-2 rounded-xl text-xs font-bold transition-all duration-300">
            الرجوع
        </a>
    </div>
</header>

<div class="max-w-6xl mx-auto p-6">
    <main class="max-w-5xl mx-auto space-y-8">
        
        <div class="bg-gray-900 p-6 rounded-[2rem] border-r-[8px] border-yellow-500 shadow-2xl relative overflow-hidden">
            <div class="absolute top-0 left-0 w-full h-full bg-yellow-500/5 pointer-events-none"></div>
            <div class="flex flex-col md:flex-row justify-between items-center gap-6 relative z-10">
                <div class="flex-grow w-full md:w-auto">
                    <h2 class="text-2xl font-black text-white mb-1 italic">قائمة المهتمين</h2>
                    <p class="text-gray-400 text-xs italic">الطلاب الذين أبدوا اهتمامهم بمنشورك.</p>
                    
                    <div class="mt-4 bg-white/5 border border-white/10 p-3 rounded-xl max-w-2xl group cursor-pointer transition-all duration-500 hover:bg-white/10">
                        <span class="text-[9px] text-yellow-500 font-bold uppercase tracking-widest block mb-1">محتوى الإعلان:</span>
                        <p class="text-gray-300 text-sm italic leading-relaxed line-clamp-2 group-hover:line-clamp-none transition-all duration-500">
                            "<?= nl2br(htmlspecialchars($post_text)) ?>"
                        </p>
                    </div>
                </div>
                
                <div class="bg-yellow-500 text-black px-8 py-3 rounded-2xl font-black text-2xl shadow-lg flex flex-col items-center min-w-[120px]">
                    <span><?= count($interested_students) ?></span>
                    <span class="text-[10px] uppercase font-bold tracking-tighter">Interested</span>
                </div>
            </div>
        </div>

        <div class="bg-green-600/10 p-5 rounded-3xl border border-yellow-500/20 backdrop-blur-sm">
            <div class="flex items-center gap-3 mb-2">
                <span class="text-xl">💡</span>
                <h3 class="text-lg font-bold text-blue-400">ملاحظة للإدارة</h3>
            </div>
            <p class="text-gray-400 text-xs italic leading-relaxed">
                في حال اكتفائكم بالعدد المطلوب وتعيين الشيف المناسب، يرجى <span class="text-white font-bold ">حذف المنشور</span>   ؛ تقديراً لوقت المتقدمين وتجنباً لانتظارهم رداً على شاغر لم يعد متاحاً و لضمان عدم استقبال طلبات إضافية
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-1 p-12">
            <?php foreach ($interested_students as $student): ?>
                <div class="student-card p-10 rounded-[2rem] hover:border-yellow-500/30 transition-all group ">
                    <div class="flex justify-between items-start mb-4">
                       
                        <span class="text-[10px] bg-green-500/20 text-green-400 px-3 py-1 rounded-full font-bold">مهتم</span>
                    </div>

                    <h3 class="text-xl font-bold text-white mb-1"><?= htmlspecialchars($student['full_name']) ?></h3>
                    <p class="text-xs text-gray-400 mb-4"><?= htmlspecialchars($student['address']) ?></p>

                    <div class="mb-4 space-y-1 border-b border-white/5 pb-3">
                         <div class="flex items-center gap-2 text-[11px]">
                            <span class="text-yellow-500 font-bold">هاتف:</span>
                            <span class="text-gray-300 tracking-wider"><?= htmlspecialchars($student['phone']) ?></span>
                        </div>
                        <?php if($student['email']): ?>
                        <div class="flex items-center gap-2 text-[11px]">
                            <span class="text-yellow-500 font-bold">بريد:</span>
                            <span class="text-gray-300 truncate"><?= htmlspecialchars($student['email']) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="space-y-4">
                        <div class="bg-black/20 rounded-xl border border-white/5 overflow-hidden">
                            <details class="group">
                                <summary class="flex justify-between items-center p-3 text-[10px] text-yellow-500 font-bold uppercase select-none cursor-pointer hover:bg-white/5 transition-all">
                                    <span>البرامج التدريبية</span>
                                    <span class="transition-transform group-open:rotate-180">▼</span>
                                </summary>
                                <div class="px-3 pb-3 max-h-32 overflow-y-auto custom-scroll">
                                    <?php if($student['enrolled_programs']): ?>
                                        <ul class="list-disc list-inside space-y-1">
                                            <?php foreach(explode('||', $student['enrolled_programs']) as $p): ?>
                                                <li class="text-[11px] text-gray-300 leading-tight"><?= htmlspecialchars($p) ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php else: ?>
                                        <p class="text-[10px] text-gray-600 italic">لا توجد برامج مسجلة</p>
                                    <?php endif; ?>
                                </div>
                            </details>
                        </div>

                        <div class="space-y-3">
                            <div>
                                <span class="text-[10px] text-gray-500 font-bold block mb-1 uppercase tracking-widest">المهارات:</span>
                                <p class="text-xs text-gray-300 italic"><?= htmlspecialchars($student['skills'] ?: 'غير مسجل') ?></p>
                            </div>
                            <div>
                                <span class="text-[10px] text-gray-500 font-bold block mb-1 uppercase tracking-widest">الخبرة:</span>
                                <p class="text-xs text-gray-300 italic"><?= htmlspecialchars($student['experience'] ?: 'لا يوجد خبرات سابقة') ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (empty($interested_students)): ?>
            <div class="text-center py-20 bg-white/5 rounded-[3rem] border border-dashed border-white/10">
                <p class="text-gray-500 font-bold italic">لا يوجد متقدمين مهتمين بهذا المنشور حالياً.</p>
            </div>
        <?php endif; ?>

    </main>
</div>

</body>
</html>