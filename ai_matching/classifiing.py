import mysql.connector
import re
import pandas as pd
from transformers import pipeline
from tqdm import tqdm

# --- 1. إعدادات قاعدة البيانات ---
db_config = {
    'host': '127.0.0.1',
    'user': 'root',
    'password': '',
    'database': 'chef-link'
}

# --- 2. دالة التنظيف المتقدمة (للمودل فقط) ---
def clean_for_ai(text):
    if pd.isna(text): return ""
    text = str(text)
    # حذف الروابط
    text = re.sub(r'http\S+|www\S+|https\S+', '', text)
    # حذف الإيموجي والرموز (ترك الحروف العربية والمسافات فقط)
    text = re.sub(r'[^\u0600-\u06FF\s]', '', text)
    return " ".join(text.split())

# --- 3. تحميل المودل الخاص بك ---
print("⏳ جارِ تحميل المودل وتجهيز النظام...")
classifier = pipeline("text-classification", model="job_post_classifier_model")

try:
    # الاتصال بـ MySQL
    conn = mysql.connector.connect(**db_config)
    cursor = conn.cursor()

    # قراءة البيانات
    df = pd.read_excel("posts.xlsx")
    
    # تحديد اسم العمود تلقائياً
    col = 'clean_post' if 'clean_post' in df.columns else df.columns[0]
    print(f"🔎 سيتم القراءة من العمود: '{col}'")
    print(f"🚀 بدء معالجة {len(df)} منشور...")

    inserted_count = 0
    ignored_count = 0

    for index, row in tqdm(df.iterrows(), total=len(df)):
        original_post = str(row[col])
        
        # تنظيف مؤقت للمودل
        temp_text = clean_for_ai(original_post)
        if len(temp_text.split()) < 3: continue

        # التصنيف
        prediction = classifier(temp_text)[0]
        label = prediction['label']
        score = prediction['score']

        # --- 4. فلتر الثقة الذكي ---
        # لن يقبل إلا LABEL_1 (وظيفة) وبشرط أن تكون الثقة أعلى من 90%
        if label == 'LABEL_1' and score > 0.80:
            
            # منع التكرار: التأكد أن المنشور غير موجود مسبقاً
            cursor.execute("SELECT id FROM social_posts WHERE content = %s LIMIT 1", (original_post,))
            if cursor.fetchone(): continue

            # التخزين في القاعدة
            sql = "INSERT INTO social_posts (content, platform, post_url) VALUES (%s, %s, %s)"
            val = (original_post, "System_AI", "N/A")
            cursor.execute(sql, val)
            inserted_count += 1
        else:
            ignored_count += 1

    # حفظ التغييرات
    conn.commit()
    
    print(f"\n✨ اكتملت العملية بنجاح!")
    print(f"✅ تم إضافة {inserted_count} إعلان وظيفي حقيقي.")
    print(f"🚫 تم استبعاد {ignored_count} منشور (إما ليست وظائف أو ثقتها ضعيفة).")

except Exception as e:
    print(f"❌ حدث خطأ: {e}")
finally:
    if 'conn' in locals() and conn.is_connected():
        cursor.close()
        conn.close()