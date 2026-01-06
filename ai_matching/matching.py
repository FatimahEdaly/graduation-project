import mysql.connector
import re
from sentence_transformers import SentenceTransformer, util

# 1. إعدادات قاعدة البيانات والموديل
db_config = {'host': '127.0.0.1', 'user': 'root', 'password': '', 'database': 'chef-link'}
model = SentenceTransformer('paraphrase-multilingual-mpnet-base-v2')

def clean_text(text):
    """تنظيف النص من الإيموجي والرموز مع الحفاظ على الكلمات"""
    text = re.sub(r'[\d_]', ' ', text)
    text = re.sub(r'[^\u0600-\u06FFa-zA-Z\s]', ' ', text)
    return re.sub(r'\s+', ' ', text).strip()

def run_silent_matching():
    try:
        conn = mysql.connector.connect(**db_config)
        cursor = conn.cursor(dictionary=True)

        cursor.execute("SELECT id, content FROM social_posts")
        posts = cursor.fetchall()
        cursor.execute("SELECT national_id, skills FROM students")
        students = cursor.fetchall()

        all_skills = []
        for student in students:
            # التعديل هنا: التقسيم يعتمد فقط على الفواصل (العربية والإنجليزية)
            parts = re.split(r',|،', student['skills'])
            
            for s in parts:
                s_clean = clean_text(s.lower())
                if len(s_clean) > 2:
                    all_skills.append({
                        'sid': student['national_id'],
                        'skill_full': s_clean,
                        'skill_vec': model.encode(s_clean, convert_to_tensor=True)
                    })

        print("🚀 جاري فحص المنشورات والربط الدلالي...")

        for post in posts:
            content = post['content'].lower()
            # تقسيم المنشور حسب السطر أو النقطة لضمان سياق منطقي
            raw_chunks = re.split(r'\n|،|\.', content)
            
            best_match_for_student = {}

            for raw_chunk in raw_chunks:
                chunk = clean_text(raw_chunk)
                if len(chunk) < 3: continue

                chunk_vec = model.encode(chunk, convert_to_tensor=True)
                
                for skill in all_skills:
                    similarity = float(util.cos_sim(skill['skill_vec'], chunk_vec))

                    # الثريشود المعتمد 0.60 لضمان دقة عالية
                    if similarity >= 0.60:
                        sid = skill['sid']
                        if sid not in best_match_for_student or similarity > best_match_for_student[sid]['score']:
                            best_match_for_student[sid] = {'score': similarity}

            # حفظ النتائج في قاعدة البيانات 
            if best_match_for_student:
                for sid, res in best_match_for_student.items():
                    cursor.execute("""
                        INSERT INTO chef_post_matches (graduate_id, post_id, similarity_score)
                        VALUES (%s, %s, %s)
                        ON DUPLICATE KEY UPDATE similarity_score = VALUES(similarity_score)
                    """, (sid, post['id'], res['score']))
                print(f"✅ تم معالجة المنشور {post['id']} بنجاح.")

        conn.commit()
        cursor.close()
        conn.close()
        print("\n✨ انتهت المهمة وحُفظت النتائج.")

    except Exception as e:
        print(f"❌ خطأ: {e}")

if __name__ == "__main__":
    run_silent_matching()