<?php
session_start();
require_once 'config.php';

// التحقق من صلاحية الوصول
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'establishment') {
    header("Location: login.php");
    exit;
}

$establishment_id = $_SESSION['user_id'];
$conn->set_charset("utf8mb4");

// جلب بيانات المنشأة لعرض الاسم في الهيدر
$est_query = $conn->prepare("SELECT full_name FROM registered_establishments WHERE id = ?");
$est_query->bind_param("i", $establishment_id);
$est_query->execute();
$establishment = $est_query->get_result()->fetch_assoc();

// --- 1. معالجة التحديث (AJAX) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_post') {
    header('Content-Type: application/json');
    $post_id = intval($_POST['post_id']);
    $new_content = trim($_POST['content']);
    
    // ملاحظة هامة: نعيد is_processed إلى 0 لكي يقوم Jenkins/Python بإعادة معالجة المنشور بعد تعديله
    $upd_stmt = $conn->prepare("UPDATE social_posts SET content = ?, is_processed = 0 WHERE id = ? AND establishment_id = ?");
    $upd_stmt->bind_param("sii", $new_content, $post_id, $establishment_id);
    
    echo json_encode(['success' => $upd_stmt->execute()]);
    exit;
}

// --- 2. معالجة الحذف ---
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $del_stmt = $conn->prepare("DELETE FROM social_posts WHERE id = ? AND establishment_id = ?");
    $del_stmt->bind_param("ii", $delete_id, $establishment_id);
    if ($del_stmt->execute()) { 
        header("Location: establishment_posts.php?msg=deleted"); 
        exit; 
    }
}

// جلب المنشورات مع عداد الطلاب المهتمين (is_interested = 1)
$sql = "SELECT sp.*, 
        (SELECT COUNT(*) FROM chef_post_matches WHERE post_id = sp.id AND is_interested = 1) as interested_count 
        FROM social_posts sp 
        WHERE sp.establishment_id = $establishment_id 
        ORDER BY sp.created_at DESC";
$posts = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>إدارة منشوراتي | شيف لينك</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700;900&display=swap" rel="stylesheet">
    <style> body { font-family: 'Tajawal', sans-serif; }
     .bottom-left-chef { position: fixed; bottom: 0; left: 0; z-index: 100; width: 250px; pointer-events: none; }
        
    </style>
</head>
<body class="bg-gray-900 text-white min-h-screen relative">
<div class="bottom-left-chef">
    <img src="images/rest.png" alt="Chef Character" class="w-full h-auto drop-shadow-2xl">
</div>

<header class="bg-gray-800/70 backdrop-blur-md shadow-md border-b border-yellow-400/30 p-4 sticky top-0 z-50">
    <div class="max-w-7xl mx-auto flex justify-between items-center relative">
        
        <a href="establishment_dashboard.php" class="flex items-center gap-3 bg-gray-800/50 py-2 px-4 rounded-2xl border border-gray-700/50 hover:border-yellow-500/50 transition-all duration-300 group cursor-pointer">
            <div class="h-8 w-8 bg-yellow-500/20 rounded-full flex items-center justify-center text-xl border border-yellow-500/30 group-hover:scale-110 transition-transform">🏢</div>
            <div class="text-right">
                <p class="text-sm font-black text-yellow-300 leading-none group-hover:text-yellow-400">
                    <?php echo htmlspecialchars($establishment['full_name'] ?? 'المنشأة'); ?>
                </p>
                <span class="text-[10px] text-gray-500 font-bold group-hover:text-yellow-500/50 transition-colors"></span>
            </div>
        </a>

        <div class="absolute left-1/2 -translate-x-1/2">
            <h1 class="text-2xl font-black text-yellow-400 tracking-tighter uppercase">CHEF-LINK</h1>
        </div>

       
    </div>
</header>

<main class="flex-grow max-w-4xl w-full mx-auto px-4 py-10 relative z-10">
   <div class="bg-gray-900 p-8 rounded-[2rem] border-r-[10px] border-yellow-500 shadow-2xl flex flex-col gap-8 relative overflow-hidden mb-12">
        <div class="absolute top-0 left-0 w-full h-full bg-yellow-500/5 pointer-events-none"></div>

        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 relative z-10">
            <div>
                <h2 class="text-3xl font-black text-white mb-2 italic">إدارة إعلاناتك الوظيفية</h2>
                <p class="text-gray-400 text-base italic leading-relaxed max-w-md">
                    هذه الصفحة تعرض جميع منشوراتك الوظيفية من فيسبوك والتي تم نشرها من داخل الموقع، مصنفة حسب تاريخ النشر.
                </p>
            </div>
            
            <div class="bg-yellow-500 text-black px-10 py-4 rounded-2xl font-black text-2xl shadow-lg flex flex-col items-center min-w-[140px]">
                <span class="leading-none"><?= $posts->num_rows ?></span>
                <span class="text-[10px] uppercase font-bold tracking-widest mt-1">Posts</span>
            </div>
        </div>
    </div>
    <div class="space-y-8">
        <?php if ($posts->num_rows > 0): ?>
            <?php while($post = $posts->fetch_assoc()): ?>
                <div class="bg-gray-800/40 backdrop-blur-md p-6 rounded-[2.5rem] border border-white/5 shadow-2xl relative overflow-hidden group">
                    
                    <div class="flex justify-between items-start mb-6">
                        <div class="space-y-2">
                            <span class="text-xs text-gray-500 block font-bold tracking-widest uppercase italic">📅 نُشر في: <?php echo date('Y-m-d', strtotime($post['created_at'])); ?></span>
                            
                            <a href="view_interested_students.php?post_id=<?php echo $post['id']; ?>" class="inline-flex items-center gap-2 bg-yellow-500/10 text-yellow-400 px-4 py-2 rounded-full text-xs font-bold border border-green-500/20 hover:bg-green-500 hover:text-white transition-all shadow-lg shadow-green-500/5">
                                <span class="relative flex h-2 w-2">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-yellow-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-2 w-2 bg-white-500"></span>
                                </span>
                                <span><?php echo $post['interested_count']; ?> طالب مهتم بالوظيفة</span>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M15 19l-7-7 7-7" />
                                </svg>
                            </a>
                        </div>

                        <div class="flex gap-2">
                            <button onclick="openEditModal(<?php echo $post['id']; ?>, `<?php echo htmlspecialchars($post['content']); ?>`)" 
                                    class="text-blue-400 hover:bg-blue-400/10 p-2 rounded-lg transition-all" title="تعديل">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </button>
                            <a href="?delete_id=<?php echo $post['id']; ?>" onclick="return confirm('هل أنت متأكد؟ سيتم حذف المنشور وجميع بيانات الطلاب الذين أبدوا اهتمامهم به.')" 
                               class="text-red-400 hover:bg-red-400/10 p-2 rounded-lg transition-all" title="حذف">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </a>
                        </div>
                    </div>

                    <div class="bg-black/20 p-5 rounded-2xl border border-white/5 italic text-gray-200 leading-relaxed shadow-inner">
                        "<?php echo nl2br(htmlspecialchars($post['content'])); ?>"
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="text-center py-20 bg-gray-800/20 rounded-[3rem] border-2 border-dashed border-gray-700">
                <p class="text-gray-500 italic">لا توجد منشورات لديك حالياً.</p>
            </div>
        <?php endif; ?>
    </div>
</main>

<div id="editModal" class="hidden fixed inset-0 bg-black/90 backdrop-blur-sm flex items-center justify-center z-[100] p-4">
    <div class="bg-gray-900 border border-yellow-500/30 rounded-[2rem] p-8 w-full max-w-lg shadow-2xl">
        <h2 class="text-xl font-black text-yellow-400 mb-2 text-right">تحديث المنشور</h2>
        <p class="text-xs text-gray-500 mb-6 text-right font-bold italic">سيقوم نظام الذكاء الاصطناعي بإعادة فحص المنشور فور حفظك للتعديلات.</p>
        
        <input type="hidden" id="editPostId">
        <textarea id="editContent" rows="6" class="w-full bg-gray-800 border border-gray-700 rounded-2xl p-4 text-white outline-none focus:border-yellow-500 text-right text-sm leading-relaxed shadow-inner transition-all"></textarea>
        
        <div class="flex gap-4 mt-8">
            <button id="saveEditBtn" class="flex-[2] bg-yellow-500 text-gray-900 font-black py-4 rounded-xl hover:bg-yellow-400 transition-all shadow-lg shadow-yellow-500/20 active:scale-95">حفظ التغييرات</button>
            <button onclick="$('#editModal').addClass('hidden')" class="flex-1 bg-gray-800 text-gray-400 py-4 rounded-xl border border-gray-700 hover:bg-gray-700 transition-all">إلغاء</button>
        </div>
    </div>
</div>

<script>
function openEditModal(id, content) {
    $('#editPostId').val(id);
    $('#editContent').val(content);
    $('#editModal').removeClass('hidden');
}

$('#saveEditBtn').click(function() {
    const postId = $('#editPostId').val();
    const newContent = $('#editContent').val();
    const btn = $(this);

    btn.prop('disabled', true).text('جاري الحفظ...');

    $.post('', { action: 'update_post', post_id: postId, content: newContent }, function(res) {
        if(res.success) {
            location.reload();
        } else {
            alert('حدث خطأ أثناء محاولة التحديث، يرجى المحاولة لاحقاً.');
            btn.prop('disabled', false).text('حفظ التغييرات');
        }
    });
});
</script>

</body>
</html>