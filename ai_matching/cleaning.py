import pandas as pd
import re

# -------------------------
# دالة تنظيف النص
# -------------------------
def clean_text(text):
    if pd.isna(text):
        return ""

    # تحويل لحروف صغيرة
    text = text.lower()

    # حذف الروابط
    text = re.sub(r"http\S+|www\S+", "", text)

    # حذف أرقام (تلفونات – رواتب)
    text = re.sub(r"\d+", "", text)

    # حذف الإيموجي والرموز غير العربية
    text = re.sub(r"[^\u0600-\u06FF\s]", " ", text)

    # إزالة الهاشتاغ
    text = text.replace("#", " ")

    # إزالة المسافات الزائدة
    text = re.sub(r"\s+", " ", text).strip()

    return text

# -------------------------
# قراءة ملف Excel
# -------------------------
file_path = "trainPost.xlsx"   # عدلي الاسم حسب ملفك
df = pd.read_excel(file_path)

# تأكد من الأعمدة
assert "post" in df.columns
assert "label" in df.columns

# -------------------------
# تطبيق التنظيف
# -------------------------
df["clean_post"] = df["post"].apply(clean_text)

# -------------------------
# حفظ نسخة نظيفة
# -------------------------
df.to_excel("job_posts_cleaned.xlsx", index=False)

print("✅ تم تنظيف النصوص بنجاح")
print(df.head())
