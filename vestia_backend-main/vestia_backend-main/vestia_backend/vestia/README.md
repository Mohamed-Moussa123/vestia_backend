# ✅ قائمة فحص مشروع VESTIA — PostgreSQL Edition

## 📊 الملفات المحولة والجاهزة:

### 🟢 REST API (`/api`)
```
✅ index.php                    — Main router (جاهز)
✅ .htaccess                    — URL rewriting (جاهز)

config/
✅ database.php                 — Database config (جاهز)

helpers/
✅ auth.php                     — Auth middleware (جاهز)
✅ response.php                 — Response helpers (جاهز)

controllers/
✅ CategoryController.php       — (جاهز)
✅ ProductController.php        — (جاهز - تعديلات: ILIKE + COALESCE + GROUP BY)
✅ CartController.php           — (لم تُرسل بعد)
✅ SavedController.php          — (جاهز - تعديل: COALESCE)
✅ OrderController.php          — (جاهز - تعديل: RETURNING id)
✅ ReviewController.php         — (جاهز - تعديلات: COALESCE + CASE WHEN)
✅ ProfileController.php        — (جاهز)
✅ AuthController.php           — (جاهز - تعديل: RETURNING id)
```

### 🟢 Admin Panel (`/admin`)
```
✅ login.php                    — (لم تُرسل بعد)
✅ dashboard.php                — (لم تُرسل بعد)
✅ categories.php               — (جاهز - تعديلات: COALESCE + GROUP BY)
✅ products.php                 — (لم تُرسل بعد)
✅ orders.php                   — (جاهز - تعديلات: ILIKE + COALESCE)
✅ users.php                    — (جاهز - تعديلات: ILIKE + COALESCE)
✅ reviews.php                  — (جاهز - تعديل: COALESCE)
✅ logout.php                   — (لم تُرسل بعد)

includes/
✅ db.php                       — (جاهز - متغيرات البيئة)
✅ header.php                   — (لم تُرسل بعد)
✅ footer.php                   — (لم تُرسل بعد)
```

### 🟢 Database (`/sql`)
```
✅ vestia_database.sql          — (جاهز - تحويل كامل من MySQL)
```

---

## 📋 الملفات المفقودة (ستحتاج لمشاركتها):

### من REST API
- [ ] CartController.php — (مطلوب: ON CONFLICT استبدال)
- [ ] api/config/database.php — (إذا اختلف عن admin)

### من Admin Panel
- [ ] login.php
- [ ] dashboard.php
- [ ] products.php
- [ ] logout.php
- [ ] includes/header.php
- [ ] includes/footer.php

---

## 🔧 التعديلات المطبقة على كل ملف:

### تعديلات عامة في كل ملفات API:
| التعديل | الملفات المتأثرة | الحالة |
|--------|---------------|--------|
| `IFNULL` → `COALESCE` | ProductController, ReviewController, SavedController, OrderController (في dashboard) | ✅ مطبق |
| `LIKE` → `ILIKE` | ProductController, orders.php, users.php | ✅ مطبق |
| `lastInsertId()` → `RETURNING id` | AuthController, OrderController | ✅ مطبق |
| `ON DUPLICATE KEY UPDATE` → `ON CONFLICT` | CartController | ⏳ بانتظار الملف |
| `GROUP BY` كامل | ProductController, SavedController, categories.php | ✅ مطبق |
| `SUM(rating=X)` → `CASE WHEN` | ReviewController | ✅ مطبق |

---

## 🚀 الخطوات التالية:

### 1️⃣ شارك الملفات المفقودة:
```
- CartController.php (من API)
- login.php (من admin)
- dashboard.php (من admin)
- products.php (من admin)
- logout.php (من admin)
- includes/header.php
- includes/footer.php
```

### 2️⃣ اختبر الـ Database:
```sql
-- في Render PostgreSQL
CREATE DATABASE vestia_db;
-- ثم استورد schema.sql
```

### 3️⃣ ضبط Environment Variables في Render:
```
DB_HOST=xxx.regional.render.com
DB_NAME=vestia_db
DB_USER=vestia_user
DB_PASS=xxx
```

### 4️⃣ اختبر API endpoints:
```bash
curl -X GET "https://your-api.render.com/categories"
```

---

## 📝 ملاحظات مهمة:

### ✅ بخصوص البيانات
- استخدم الملف الثاني (schema.sql الذي أرسلته) لأنه يحتوي على بيانات اختبار حقيقية
- أو استخدم الملف الأول مع إضافة حقول OTP

### ✅ بخصوص Render
- ستحتاج متغيرات البيئة في Settings → Environment Variables
- Database يتم إعداده تلقائياً عند الربط

### ✅ بخصوص CartController
- تأكد من وجود UNIQUE constraint: `(user_id, product_id, size)`
- هو موجود في schema.sql

---

## 📊 إجمالي الملفات:

| النوع | الملفات | الجاهزة | المفقودة |
|-------|--------|--------|---------|
| PHP | 20+ | 14 | 6 |
| SQL | 1 | 1 | 0 |
| Config | 2 | 2 | 0 |
| **المجموع** | **23+** | **17** | **6** |

---

**متى تكون جاهزاً للرفع على Render؟**
✅ بعد مشاركة الـ 6 ملفات المفقودة وتحويلها
