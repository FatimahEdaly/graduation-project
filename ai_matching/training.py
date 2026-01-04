import pandas as pd
import torch
from sklearn.model_selection import train_test_split
from sklearn.metrics import accuracy_score, classification_report
from transformers import (
    AutoTokenizer,
    AutoModelForSequenceClassification,
    Trainer,
    TrainingArguments
)

# -------------------------
# تحميل البيانات
# -------------------------
df = pd.read_excel("job_posts_cleaned.xlsx")

texts = df["clean_post"].tolist()
labels = df["label"].tolist()

# -------------------------
# تقسيم البيانات
# -------------------------
X_train, X_test, y_train, y_test = train_test_split(
    texts, labels, test_size=0.2, random_state=42, stratify=labels
)

# -------------------------
# تحميل Tokenizer + Model
# -------------------------
MODEL_NAME = "aubmindlab/bert-base-arabertv02"

tokenizer = AutoTokenizer.from_pretrained(MODEL_NAME)
model = AutoModelForSequenceClassification.from_pretrained(
    MODEL_NAME,
    num_labels=2
)

# -------------------------
# Dataset class
# -------------------------
class JobDataset(torch.utils.data.Dataset):
    def __init__(self, texts, labels):
        self.encodings = tokenizer(
            texts,
            truncation=True,
            padding=True,
            max_length=128
        )
        self.labels = labels

    def __len__(self):
        return len(self.labels)

    def __getitem__(self, idx):
        item = {key: torch.tensor(val[idx]) for key, val in self.encodings.items()}
        item["labels"] = torch.tensor(self.labels[idx])
        return item

train_dataset = JobDataset(X_train, y_train)
test_dataset = JobDataset(X_test, y_test)

# -------------------------
# إعدادات التدريب
# -------------------------
# -------------------------
# إعدادات التدريب
# -------------------------
training_args = TrainingArguments(
    output_dir="./job_classifier",
    eval_strategy="epoch",        # <--- تم تغييرها من evaluation_strategy إلى eval_strategy
    save_strategy="epoch",
    learning_rate=2e-5,
    per_device_train_batch_size=8,
    per_device_eval_batch_size=8,
    num_train_epochs=3,
    weight_decay=0.01,
    logging_dir="./logs",
    load_best_model_at_end=True,
    metric_for_best_model="accuracy"
)
# -------------------------
# دالة التقييم
# -------------------------
def compute_metrics(eval_pred):
    logits, labels = eval_pred
    preds = logits.argmax(axis=1)
    return {
        "accuracy": accuracy_score(labels, preds)
    }

# -------------------------
# Trainer
# -------------------------
trainer = Trainer(
    model=model,
    args=training_args,
    train_dataset=train_dataset,
    eval_dataset=test_dataset,
    compute_metrics=compute_metrics
)

# -------------------------
# بدء التدريب
# -------------------------
trainer.train()

# -------------------------
# تقييم نهائي
# -------------------------
preds = trainer.predict(test_dataset)
y_pred = preds.predictions.argmax(axis=1)

print("\n📊 تقرير التصنيف:\n")
print(classification_report(y_test, y_pred, target_names=["ليس إعلان", "إعلان وظيفي"]))

# -------------------------
# حفظ المودل
# -------------------------
trainer.save_model("job_post_classifier_model")
tokenizer.save_pretrained("job_post_classifier_model")

print("✅ تم تدريب وحفظ المودل بنجاح")
