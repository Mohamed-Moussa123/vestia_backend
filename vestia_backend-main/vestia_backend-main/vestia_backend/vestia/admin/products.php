<?php
session_start();
require_once __DIR__ . '/includes/db.php';
adminCheck();
$db = db();

// ══════════════════════════════════════════════
//  ☁️  Cloudinary Configuration
//  ضع بياناتك هنا أو في ملف .env
// ══════════════════════════════════════════════
define('CLOUDINARY_CLOUD_NAME', 'dyaiu7env');
define('CLOUDINARY_API_KEY',    '368529122995758');
define('CLOUDINARY_API_SECRET', 'I-Udh8Hr06mSqWhkbhQeyTk1O5s');
define('CLOUDINARY_FOLDER',     'vestia/products');    // مجلد التخزين في Cloudinary

/**
 * رفع صورة إلى Cloudinary عبر REST API مباشرةً (بدون مكتبة خارجية)
 * يُعيد الـ secure_url عند النجاح، أو false عند الفشل
 */
function uploadToCloudinary(string $fileTmpPath, string $originalName): string|false
{
    $timestamp  = time();
    $folder     = CLOUDINARY_FOLDER;
    $publicId   = $folder . '/' . pathinfo($originalName, PATHINFO_FILENAME) . '_' . uniqid();

    // توقيع الطلب
    $paramsToSign = "folder={$folder}&public_id={$publicId}&timestamp={$timestamp}";
    $signature    = sha1($paramsToSign . CLOUDINARY_API_SECRET);

    $uploadUrl = 'https://api.cloudinary.com/v1_1/' . CLOUDINARY_CLOUD_NAME . '/image/upload';

    $postFields = [
        'file'       => new CURLFile($fileTmpPath),
        'api_key'    => CLOUDINARY_API_KEY,
        'timestamp'  => $timestamp,
        'signature'  => $signature,
        'folder'     => $folder,
        'public_id'  => $publicId,
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $uploadUrl,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $postFields,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) return false;

    $data = json_decode($response, true);
    return $data['secure_url'] ?? false;   // رابط HTTPS مباشر من Cloudinary CDN
}

// ── Handle actions ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $name     = sanitize($_POST['name']    ?? '');
        $nameAr   = sanitize($_POST['name_ar'] ?? '');
        $nameFr   = sanitize($_POST['name_fr'] ?? '');
        $catId    = (int)($_POST['category_id'] ?? 0) ?: null;
        $price    = (float)($_POST['price']     ?? 0);
        $oldPrice = $_POST['old_price'] !== '' ? (float)$_POST['old_price'] : null;
        $desc     = sanitize($_POST['description'] ?? '');
        $sizes    = sanitize($_POST['sizes']    ?? 'S,M,L,XL,XXL');
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        // الصورة: يُحتفظ بالقديمة إلا إذا رُفعت جديدة
        $imageUrl = sanitize($_POST['current_image_url'] ?? '');

        if (!empty($_FILES['image_file']['name'])) {
            $file    = $_FILES['image_file'];
            $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            $maxSize = 5 * 1024 * 1024; // 5 MB

            if (!in_array($file['type'], $allowed)) {
                flash('error', 'نوع الصورة غير مدعوم. المسموح: JPG, PNG, WEBP, GIF.');
            } elseif ($file['size'] > $maxSize) {
                flash('error', 'حجم الصورة كبير جداً. الحد الأقصى 5 MB.');
            } elseif ($file['error'] !== UPLOAD_ERR_OK) {
                flash('error', 'فشل في رفع الصورة. حاول مرة أخرى.');
            } else {
                // ☁️ الرفع إلى Cloudinary
                $cloudUrl = uploadToCloudinary($file['tmp_name'], $file['name']);
                if ($cloudUrl) {
                    $imageUrl = $cloudUrl;
                    // الرابط جاهز للحفظ في DB واستخدامه مباشرةً في التطبيق والإدمن
                } else {
                    flash('error', 'فشل الرفع إلى Cloudinary. تحقق من بيانات الاعتماد.');
                }
            }
        }

        if (!$name || !$price) {
            flash('error', 'الاسم والسعر مطلوبان.');
        } else {
            if ($action === 'add') {
                $db->prepare('INSERT INTO products (category_id, name, name_ar, name_fr, description, price, old_price, image_url, sizes, is_active) VALUES (?,?,?,?,?,?,?,?,?,?)')
                   ->execute([$catId, $name, $nameAr ?: null, $nameFr ?: null, $desc, $price, $oldPrice, $imageUrl, $sizes, $isActive]);
                flash('success', 'تمت إضافة المنتج بنجاح!');
            } else {
                $id = (int)$_POST['id'];
                $db->prepare('UPDATE products SET category_id=?, name=?, name_ar=?, name_fr=?, description=?, price=?, old_price=?, image_url=?, sizes=?, is_active=? WHERE id=?')
                   ->execute([$catId, $name, $nameAr ?: null, $nameFr ?: null, $desc, $price, $oldPrice, $imageUrl, $sizes, $isActive, $id]);
                flash('success', 'تم تحديث المنتج!');
            }
            header('Location: /vestia_backend/vestia/admin/products.php'); exit;
        }
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $db->prepare('UPDATE products SET is_active=0 WHERE id=?')->execute([$id]);
        flash('success', 'تم إخفاء المنتج.');
        header('Location: /vestia_backend/vestia/admin/products.php'); exit;
    }
}

// Edit mode
$editProduct = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare('SELECT * FROM products WHERE id=?');
    $stmt->execute([(int)$_GET['edit']]);
    $editProduct = $stmt->fetch();
}

// Filters
$search  = trim($_GET['search']   ?? '');
$catFilt = (int)($_GET['category'] ?? 0);
$page    = max(1, (int)($_GET['page'] ?? 1));
$limit   = 15;
$offset  = ($page - 1) * $limit;

$where  = ['p.is_active=1'];
$params = [];
if ($search)  { $where[] = 'p.name ILIKE ?'; $params[] = "%$search%"; }
if ($catFilt) { $where[] = 'p.category_id=?'; $params[] = $catFilt; }
$whereSQL = implode(' AND ', $where);

$total = $db->prepare("SELECT COUNT(*) FROM products p WHERE $whereSQL");
$total->execute($params); $total = (int)$total->fetchColumn();
$pages = max(1, (int)ceil($total / $limit));

$stmt = $db->prepare(
    "SELECT p.*, c.name AS cat_name FROM products p
     LEFT JOIN categories c ON c.id=p.category_id
     WHERE $whereSQL ORDER BY p.id DESC LIMIT $limit OFFSET $offset"
);
$stmt->execute($params);
$products = $stmt->fetchAll();

$categories = $db->query("SELECT * FROM categories WHERE slug != 'all' ORDER BY sort_order")->fetchAll();

$pageTitle = 'Products';
include __DIR__ . '/includes/header.php';
?>

<style>
.img-upload-area {
  position: relative;
  border: 2px dashed #d1d5db;
  border-radius: 12px;
  overflow: hidden;
  background: #f9fafb;
  transition: border-color .2s, background .2s;
  cursor: pointer;
  min-height: 120px;
}
.img-upload-area:hover { border-color: #111827; background: #f3f4f6; }

.img-upload-area input[type="file"] {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  opacity: 0;
  cursor: pointer;
  z-index: 2;
}

.img-upload-placeholder {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 28px 16px;
  gap: 6px;
  pointer-events: none;
  user-select: none;
}
.img-upload-placeholder .icon  { font-size: 34px; color: #9ca3af; }
.img-upload-placeholder .label { font-size: 13px; font-weight: 600; color: #374151; }
.img-upload-placeholder .hint  { font-size: 11.5px; color: #9ca3af; text-align: center; }

/* حالة جارٍ الرفع */
.img-upload-uploading {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 28px 16px;
  gap: 8px;
  pointer-events: none;
}
.img-upload-uploading .spinner {
  width: 32px; height: 32px;
  border: 3px solid #e5e7eb;
  border-top-color: #111827;
  border-radius: 50%;
  animation: spin .7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
.img-upload-uploading span { font-size: 12px; color: #6b7280; }

.img-upload-preview { display: none; position: relative; }
.img-upload-preview img {
  width: 100%;
  max-height: 200px;
  object-fit: cover;
  display: block;
}
.img-upload-preview .remove-btn {
  position: absolute;
  top: 8px; right: 8px;
  background: rgba(220,38,38,.8);
  color: #fff;
  border: none;
  border-radius: 50%;
  width: 30px; height: 30px;
  font-size: 15px;
  cursor: pointer;
  z-index: 3;
  display: flex; align-items: center; justify-content: center;
  pointer-events: all;
}
.img-upload-preview .remove-btn:hover { background: rgb(220,38,38); }
.img-upload-preview .change-btn {
  position: absolute;
  bottom: 8px; right: 8px;
  background: rgba(0,0,0,.6);
  color: #fff;
  border: none;
  border-radius: 6px;
  font-size: 11.5px;
  padding: 4px 10px;
  cursor: pointer;
  z-index: 3;
  display: flex; align-items: center; gap: 4px;
  pointer-events: all;
}
.img-upload-preview .change-btn:hover { background: rgba(0,0,0,.85); }

/* شارة Cloudinary */
.cloudinary-badge {
  display: inline-flex; align-items: center; gap: 4px;
  font-size: 10.5px; color: #0ea5e9;
  background: #f0f9ff; border: 1px solid #bae6fd;
  border-radius: 20px; padding: 2px 8px;
  margin-top: 4px;
}
</style>

<?php $succ=flash('success'); $err=flash('error'); ?>
<?php if($succ): ?><div class="alert alert-success" data-auto-dismiss><?= htmlspecialchars($succ) ?></div><?php endif; ?>
<?php if($err):  ?><div class="alert alert-danger"  data-auto-dismiss><?= htmlspecialchars($err)  ?></div><?php endif; ?>

<div class="row g-4">

  <!-- Product Form -->
  <div class="col-xl-4 col-lg-5">
    <div class="card">
      <div class="card-header">
        <h5><i class="bi bi-bag-plus me-2"></i><?= $editProduct ? 'Edit Product' : 'Add Product' ?></h5>
        <?php if ($editProduct): ?>
          <a href="/admin/products.php" class="btn btn-sm btn-outline-secondary ms-auto">Cancel</a>
        <?php endif; ?>
      </div>
      <div class="p-4">
        <form method="POST" enctype="multipart/form-data" id="productForm">
          <input type="hidden" name="_csrf" value="<?= csrf() ?>">
          <input type="hidden" name="action" value="<?= $editProduct ? 'edit' : 'add' ?>">
          <input type="hidden" name="current_image_url" id="currentImageUrl" value="<?= htmlspecialchars($editProduct['image_url'] ?? '') ?>">
          <?php if ($editProduct): ?><input type="hidden" name="id" value="<?= $editProduct['id'] ?>"><?php endif; ?>

          <div class="mb-3">
            <label class="form-label">Product Name (EN) *</label>
            <input type="text" name="name" class="form-control"
                   value="<?= htmlspecialchars($editProduct['name'] ?? '') ?>" required>
          </div>

          <div class="mb-3">
            <label class="form-label">الاسم بالعربية <span class="text-muted" style="font-size:12px">(اختياري)</span></label>
            <input type="text" name="name_ar" class="form-control" dir="rtl"
                   placeholder="مثال: فستان صيفي"
                   value="<?= htmlspecialchars($editProduct['name_ar'] ?? '') ?>">
          </div>

          <div class="mb-3">
            <label class="form-label">Nom en Français <span class="text-muted" style="font-size:12px">(optionnel)</span></label>
            <input type="text" name="name_fr" class="form-control"
                   placeholder="ex: Robe d'été"
                   value="<?= htmlspecialchars($editProduct['name_fr'] ?? '') ?>">
          </div>

          <div class="mb-3">
            <label class="form-label">Category</label>
            <select name="category_id" class="form-select">
              <option value="">— None —</option>
              <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= ($editProduct['category_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($cat['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="row g-2 mb-3">
            <div class="col">
              <label class="form-label">Price *</label>
              <input type="number" name="price" class="form-control" step="0.01"
                     value="<?= $editProduct['price'] ?? '' ?>" required>
            </div>
            <div class="col">
              <label class="form-label">Old Price</label>
              <input type="number" name="old_price" class="form-control" step="0.01"
                     value="<?= $editProduct['old_price'] ?? '' ?>" placeholder="Optional">
            </div>
          </div>

          <!-- ── Image Upload → Cloudinary ── -->
          <div class="mb-3">
            <label class="form-label d-flex align-items-center gap-2">
              Product Image
              <span class="cloudinary-badge">
                <i class="bi bi-cloud-arrow-up-fill"></i> Cloudinary CDN
              </span>
            </label>
            <div class="img-upload-area" id="uploadArea">
              <input type="file" name="image_file" id="imageFileInput"
                     accept="image/*"
                     onchange="handleImageSelect(this)">

              <!-- حالة 1: لا توجد صورة -->
              <div class="img-upload-placeholder" id="uploadPlaceholder">
                <span class="icon"><i class="bi bi-cloud-upload"></i></span>
                <span class="label">اضغط لاختيار صورة</span>
                <span class="hint">JPG · PNG · WEBP · GIF — حتى 5 MB<br>سيتم الرفع إلى Cloudinary تلقائياً</span>
              </div>

              <!-- حالة 2: جارٍ الرفع -->
              <div class="img-upload-uploading" id="uploadingIndicator" style="display:none">
                <div class="spinner"></div>
                <span>جارٍ الرفع إلى Cloudinary...</span>
              </div>

              <!-- حالة 3: معاينة الصورة -->
              <div class="img-upload-preview" id="uploadPreview">
                <img id="previewImg" src="" alt="Preview">
                <button type="button" class="remove-btn" onclick="removeImage(event)" title="Remove">
                  <i class="bi bi-x"></i>
                </button>
                <button type="button" class="change-btn" onclick="triggerPicker(event)">
                  <i class="bi bi-arrow-repeat"></i> تغيير
                </button>
              </div>
            </div>
          </div>
          <!-- ── / Image Upload ── -->

          <div class="mb-3">
            <label class="form-label">Sizes (comma-separated)</label>
            <input type="text" name="sizes" class="form-control"
                   value="<?= htmlspecialchars($editProduct['sizes'] ?? 'S,M,L,XL,XXL') ?>">
          </div>
          <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($editProduct['description'] ?? '') ?></textarea>
          </div>
          <div class="form-check mb-4">
            <input class="form-check-input" type="checkbox" name="is_active" id="isActive"
                   <?= ($editProduct['is_active'] ?? 1) ? 'checked' : '' ?>>
            <label class="form-check-label" for="isActive">Active (visible in app)</label>
          </div>
          <button type="submit" class="btn btn-dark w-100" id="submitBtn">
            <i class="bi bi-<?= $editProduct ? 'check2' : 'plus-lg' ?> me-2"></i><?= $editProduct ? 'Save Changes' : 'Add Product' ?>
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- Products Table -->
  <div class="col-xl-8 col-lg-7">
    <div class="card">
      <div class="card-header justify-content-between flex-wrap gap-2">
        <h5><i class="bi bi-bag me-2"></i>Products
          <span class="text-muted fw-normal" style="font-size:13px">(<?= $total ?>)</span>
        </h5>
        <form class="d-flex gap-2" method="GET">
          <input type="text" name="search" class="form-control form-control-sm"
                 placeholder="Search..." value="<?= htmlspecialchars($search) ?>" style="width:160px">
          <select name="category" class="form-select form-select-sm" style="width:130px">
            <option value="">All Categories</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?= $cat['id'] ?>" <?= $catFilt == $cat['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($cat['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <button class="btn btn-sm btn-dark">Filter</button>
          <?php if ($search || $catFilt): ?>
            <a href="/admin/products.php" class="btn btn-sm btn-outline-secondary">Clear</a>
          <?php endif; ?>
        </form>
      </div>
      <div class="table-responsive">
        <table class="table">
          <thead>
            <tr>
              <th>Product</th><th>AR / FR</th><th>Category</th><th>Price</th><th>Status</th><th>Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($products as $p): ?>
            <tr>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <?php if ($p['image_url']): ?>
                    <?php
                      // ☁️ إذا كان الرابط من Cloudinary أضف تحويل تلقائي للـ thumbnail
                      $thumbUrl = $p['image_url'];
                      if (str_contains($thumbUrl, 'cloudinary.com')) {
                          // تحويل تلقائي: 80x80 crop + جودة auto
                          $thumbUrl = str_replace('/upload/', '/upload/w_80,h_80,c_fill,q_auto,f_auto/', $thumbUrl);
                      }
                    ?>
                    <img src="<?= htmlspecialchars($thumbUrl) ?>" class="product-thumb" alt="">
                  <?php else: ?>
                    <div class="product-thumb-placeholder"><i class="bi bi-image"></i></div>
                  <?php endif; ?>
                  <div>
                    <div class="fw-600" style="font-size:13px"><?= htmlspecialchars($p['name']) ?></div>
                    <div style="font-size:11px;color:#9ca3af">#<?= $p['id'] ?></div>
                    <?php if ($p['image_url'] && str_contains($p['image_url'], 'cloudinary.com')): ?>
                      <span style="font-size:10px;color:#0ea5e9"><i class="bi bi-cloud-check-fill"></i> CDN</span>
                    <?php endif; ?>
                  </div>
                </div>
              </td>
              <td style="font-size:12px;line-height:1.6">
                <?php if ($p['name_ar'] ?? null): ?>
                  <div dir="rtl" style="color:#374151"><?= htmlspecialchars($p['name_ar']) ?></div>
                <?php endif; ?>
                <?php if ($p['name_fr'] ?? null): ?>
                  <div style="color:#6b7280"><?= htmlspecialchars($p['name_fr']) ?></div>
                <?php endif; ?>
                <?php if (!($p['name_ar'] ?? null) && !($p['name_fr'] ?? null)): ?>
                  <span style="color:#d1d5db">—</span>
                <?php endif; ?>
              </td>
              <td style="font-size:13px"><?= htmlspecialchars($p['cat_name'] ?? '—') ?></td>
              <td>
                <div class="fw-700" style="font-size:13px"><?= formatPrice($p['price']) ?></div>
                <?php if ($p['old_price']): ?>
                  <div style="font-size:11px;color:#9ca3af;text-decoration:line-through"><?= formatPrice($p['old_price']) ?></div>
                <?php endif; ?>
              </td>
              <td>
                <span style="font-size:11px;padding:3px 8px;border-radius:20px;font-weight:600;
                             background:<?= $p['is_active'] ? '#dcfce7' : '#f3f4f6' ?>;
                             color:<?= $p['is_active'] ? '#166534' : '#6b7280' ?>">
                  <?= $p['is_active'] ? 'Active' : 'Inactive' ?>
                </span>
              </td>
              <td>
                <div class="d-flex gap-1">
                  <a href="/admin/products.php?edit=<?= $p['id'] ?>" class="btn-icon"><i class="bi bi-pencil"></i></a>
                  <form method="POST" class="d-inline">
                    <input type="hidden" name="_csrf" value="<?= csrf() ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                    <button type="submit" class="btn-icon danger"
                            data-confirm="Remove this product?"><i class="bi bi-trash"></i></button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($products)): ?>
            <tr><td colspan="6" class="text-center text-muted py-4">No products found</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
      <?php if ($pages > 1): ?>
      <div class="p-3 d-flex justify-content-center">
        <nav><ul class="pagination mb-0">
          <?php for ($i = 1; $i <= $pages; $i++): ?>
            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
              <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&category=<?= $catFilt ?>"><?= $i ?></a>
            </li>
          <?php endfor; ?>
        </ul></nav>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
// ── Preview Logic ──
document.addEventListener('DOMContentLoaded', function () {
  const existing = document.getElementById('currentImageUrl').value;
  if (existing) showPreview(existing);
});

function handleImageSelect(input) {
  if (!input.files || !input.files[0]) return;
  // نعرض معاينة فورية من الذاكرة (الرفع الفعلي يتم عند submit)
  const reader = new FileReader();
  reader.onload = e => showPreview(e.target.result);
  reader.readAsDataURL(input.files[0]);
}

function showPreview(src) {
  document.getElementById('previewImg').src = src;
  document.getElementById('uploadPlaceholder').style.display    = 'none';
  document.getElementById('uploadingIndicator').style.display   = 'none';
  document.getElementById('uploadPreview').style.display        = 'block';
}

function removeImage(e) {
  e.stopPropagation();
  document.getElementById('imageFileInput').value    = '';
  document.getElementById('currentImageUrl').value   = '';
  document.getElementById('previewImg').src          = '';
  document.getElementById('uploadPreview').style.display        = 'none';
  document.getElementById('uploadingIndicator').style.display   = 'none';
  document.getElementById('uploadPlaceholder').style.display    = 'flex';
}

function triggerPicker(e) {
  e.stopPropagation();
  document.getElementById('imageFileInput').click();
}

// ── عرض مؤشر الرفع عند إرسال الفورم ──
document.getElementById('productForm').addEventListener('submit', function () {
  const hasNewFile = document.getElementById('imageFileInput').files.length > 0;
  if (hasNewFile) {
    document.getElementById('uploadPreview').style.display      = 'none';
    document.getElementById('uploadPlaceholder').style.display  = 'none';
    document.getElementById('uploadingIndicator').style.display = 'flex';
    document.getElementById('submitBtn').disabled = true;
    document.getElementById('submitBtn').innerHTML =
      '<span class="spinner-border spinner-border-sm me-2"></span>جارٍ الحفظ...';
  }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
