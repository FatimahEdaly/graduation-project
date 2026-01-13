<?php
session_start();
require_once 'config.php';

// التحقق من صلاحية الوصول
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

// التأكد من ملكية المنشور
$checkPost = $conn->prepare("SELECT id FROM social_posts WHERE id = ? AND establishment_id = ?");
$checkPost->bind_param("ii", $post_id, $establishment_id);
$checkPost->execute();
if ($checkPost->get_result()->num_rows === 0) {
    die("غير مسموح لك بالوصول لهذه البيانات.");
}

// استعلام جلب الطلاب مع (اسم البرنامج + نوعه) مدمجين
$sql = "SELECT s.*, cpm.matched_at, cpm.similarity_score, sp.content as post_content,
        (SELECT GROUP_CONCAT(CONCAT(tp.program_name, ' [', tp.program_type, ']') SEPARATOR '||') 
         FROM student_programs stp 
         JOIN training_programs tp ON stp.program_id = tp.id 
         WHERE stp.student_id = s.national_id) as enrolled_programs
        FROM students s
        JOIN chef_post_matches cpm ON s.national_id = cpm.graduate_id
        JOIN social_posts sp ON cpm.post_id = sp.id
        WHERE cpm.post_id = ? AND cpm.is_interested = 1
        ORDER BY cpm.similarity_score DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $post_id);
$stmt->execute();
$interested_students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// جلب نص المنشور للعرض في الـ Header
$post_text = "";
if (!empty($interested_students)) {
    $post_text = $interested_students[0]['post_content'];
} else {
    $p_query = $conn->prepare("SELECT content FROM social_posts WHERE id = ?");
    $p_query->bind_param("i", $post_id);
    $p_query->execute();
    $p_res = $p_query->get_result()->fetch_assoc();
    $post_text = $p_res['content'] ?? "لا يوجد نص للمنشور";
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>المهتمين بالوظيفة | شيف لينك</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Tajawal', sans-serif; background-color: #0f172a; }
        .student-card { background: rgba(30, 41, 59, 0.7); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.1); transition: all 0.3s ease; }
        .student-card:hover { border-color: #f59e0b; transform: translateY(-5px); }
        details summary::-webkit-details-marker { display: none; }
    </style>
</head>
<body class="text-white min-h-screen pb-12">

<header class="bg-slate-900/80 backdrop-blur-md p-6 border-b border-yellow-500/20 sticky top-0 z-50">
    <div class="max-w-6xl mx-auto flex justify-between items-center">
        <a href="establishment_posts.php" class="text-gray-400 hover:text-white flex items-center gap-2 transition-all font-bold">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            <span>الرجوع للمنشورات</span>
        </a>
        <h1 class="text-xl font-black text-yellow-500 uppercase tracking-widest italic">CHEF-LINK</h1>
    </div>
</header>

<main class="max-w-6xl mx-auto p-6">
    
    <div class="bg-gray-900 p-8 rounded-[2rem] border-r-[10px] border-yellow-500 shadow-2xl flex flex-col gap-6 relative overflow-hidden mb-12">
        <div class="absolute top-0 left-0 w-full h-full bg-yellow-500/5 pointer-events-none"></div>
        <div class="flex flex-col md:flex-row justify-between items-center gap-8 relative z-10">
            <div class="text-right flex-grow">
                <h2 class="text-3xl font-black text-white mb-4 italic tracking-tighter">قائمة المهتمين بالوظيفة</h2>
                <div class="bg-white/5 border border-white/10 p-6 rounded-2xl mb-3 group max-w-3xl">
                    <span class="text-[11px] text-yellow-500 font-black uppercase tracking-widest">نص المنشور المختار:</span>
                    <p class="text-gray-200 text-base font-medium italic leading-relaxed mt-2">
                        "<?= nl2br(htmlspecialchars($post_text)) ?>"
                    </p>
                </div>
            </div>
            <div class="bg-yellow-500 text-black px-8 py-5 rounded-[2rem] font-black text-4xl shadow-lg flex flex-col items-center min-w-[130px] shrink-0 border-4 border-black/5">
                <span class="leading-none"><?= count($interested_students) ?></span>
                <span class="text-[11px] uppercase font-black tracking-widest mt-2 border-t-2 border-black/20 pt-1 w-full text-center">Applicants</span>
            </div>
        </div>
    </div>

    <?php if (empty($interested_students)): ?>
        <div class="text-center py-24 bg-slate-800/20 rounded-[3rem] border-2 border-dashed border-slate-700 text-gray-500">
            لا يوجد متقدمين حالياً.
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <?php foreach ($interested_students as $student): ?>
                <div class="student-card p-8 rounded-[2.5rem] flex flex-col justify-between">
                    <div>
                        <div class="flex justify-between items-center mb-6">
                            <div class="bg-yellow-500/20 text-yellow-500 px-4 py-1 rounded-full text-[10px] font-black uppercase italic">
                                مطابقة: <?= number_format($student['similarity_score'] * 100, 0) ?>%
                            </div>
                            <span class="text-[10px] text-gray-500 font-bold"><?= date('d/m/Y', strtotime($student['matched_at'])) ?></span>
                        </div>

                        <h3 class="text-2xl font-black text-white mb-2 leading-none"><?= htmlspecialchars($student['full_name']) ?></h3>
                        <p class="text-sm text-yellow-500/70 mb-6 flex items-center gap-2 italic">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            </svg>
                            <?= htmlspecialchars($student['address']) ?>
                        </p>
                        
                        <div class="space-y-4 mb-6">
                            <div class="bg-black/30 rounded-xl overflow-hidden border border-white/5">
                                <details class="group">
                                    <summary class="flex items-center justify-between p-3 cursor-pointer list-none hover:bg-white/5">
                                        <span class="text-[10px] uppercase font-black text-yellow-500 tracking-widest flex items-center gap-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                                            </svg>
                                            السجل التدريبي (في مركز مريم هاشم)
                                        </span>
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-500 group-open:rotate-180 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </summary>
                                    <div class="p-3 pt-0 space-y-2">
                                        <?php if (!empty($student['enrolled_programs'])): 
                                            $programs = explode('||', $student['enrolled_programs']);
                                            foreach ($programs as $prog): ?>
                                                <div class="text-[11px] text-gray-300 bg-white/5 p-2 rounded-lg border-r-2 border-yellow-500 italic">
                                                    <?= htmlspecialchars($prog) ?>
                                                </div>
                                            <?php endforeach; 
                                        else: ?>
                                            <p class="text-[10px] text-gray-600 italic p-2 text-center">لا توجد برامج مسجلة</p>
                                        <?php endif; ?>
                                    </div>
                                </details>
                            </div>

                            <div class="border-r-2 border-yellow-500/30 pr-4">
                                <h4 class="text-[10px] uppercase font-black text-gray-500 mb-1 tracking-widest">المهارات:</h4>
                                <p class="text-xs text-gray-300 leading-relaxed italic">"<?= htmlspecialchars($student['skills']) ?>"</p>
                            </div>

                            <div class="border-r-2 border-white/10 pr-4">
                                <h4 class="text-[10px] uppercase font-black text-gray-500 mb-1 tracking-widest">الخبرة العملية:</h4>
                                <p class="text-xs text-gray-400 italic leading-relaxed">
                                    <?= htmlspecialchars($student['experience'] ?: 'لا توجد خبرة سابقة مسجلة') ?>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 pt-6 border-t border-white/5 space-y-3">
                        <div class="flex items-center gap-4 bg-slate-900/40 p-3 rounded-2xl border border-white/5">
                            <div class="bg-yellow-500/10 p-2 rounded-xl text-yellow-500">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                </svg>
                            </div>
                            <div>
                                <span class="text-[9px] text-gray-500 block font-bold uppercase">الهاتف</span>
                                <span class="text-sm font-black text-gray-200"><?= htmlspecialchars($student['phone']) ?></span>
                            </div>
                        </div>

                        <?php if (!empty($student['email'])): ?>
                        <div class="flex items-center gap-4 bg-slate-900/40 p-3 rounded-2xl border border-white/5">
                            <div class="bg-blue-500/10 p-2 rounded-xl text-blue-400">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <div class="overflow-hidden">
                                <span class="text-[9px] text-gray-500 block font-bold uppercase">الايميل</span>
                                <span class="text-xs font-bold text-blue-300 truncate block"><?= htmlspecialchars($student['email']) ?></span>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

</body>
</html>