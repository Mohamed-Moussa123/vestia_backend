<?php
session_start();
require_once __DIR__ . '/includes/db.php';
adminCheck();
$db = db();

// ── Handle actions ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $name     = sanitize($_POST['name']    ?? '');
        $nameAr   = sanitize($_POST['name_ar'] ?? ''); // ✅ إصلاح 4
        $nameFr   = sanitize($_POST['name_fr'] ?? ''); // ✅ إصلاح 4
        $catId    = (int)($_POST['category_id'] ?? 0) ?: null;
        $price    = (float)($_POST['price']     ?? 0);
        $oldPrice = $_POST['old_price'] !== '' ? (float)$_POST['old_price'] : null;
        $desc     = sanitize($_POST['description'] ?? '');
        $sizes    = sanitize($_POST['sizes']    ?? 'S,M,L,XL,XXL');
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        // Keep existing image unless a new file is uploaded
        $imageUrl = sanitize($_POST['current_image_url'] ?? '');

        if (!empty($_FILES['image_file']['name'])) {
            $file    = $_FILES['image_file'];
            $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            $maxSize = 5 * 1024 * 1024; // 5 MB

            if (!in_array($file['type'], $allowed)) {
                flash('error', 'Invalid image type. Allowed: JPG, PNG, WEBP, GIF.');
            } elseif ($file['size'] > $maxSize) {
                flash('error', 'Image is too large. Max size is 5 MB.');
            } elseif ($file['error'] !== UPLOAD_ERR_OK) {
                flash('error', 'Upload error. Please try again.');
            } else {
                $uploadDir = __DIR__ . '/../uploads/products/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = uniqid('prod_', true) . '.' . strtolower($ext);
                if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                    $imageUrl = '/vestia_backend/vestia/uploads/products/' . $filename;
                } else {
                    flash('error', 'Failed to save the uploaded image.');
                }
            }
        }

        if (!$name || !$price) {
            flash('error', 'Name and price are required.');
        } else {
            if ($action === 'add') {
                // ✅ إصلاح 4 — حفظ name_ar و name_fr
                $db->prepare('INSERT INTO products (category_id, name, name_ar, name_fr, description, price, old_price, image_url, sizes, is_active) VALUES (?,?,?,?,?,?,?,?,?,?)')
                   ->execute([$catId, $name, $nameAr ?: null, $nameFr ?: null, $desc, $price, $oldPrice, $imageUrl, $sizes, $isActive]);
                flash('success', 'Product added successfully!');
            } else {
                $id = (int)$_POST['id'];
                // ✅ إصلاح 4 — تحديث name_ar و name_fr
                $db->prepare('UPDATE products SET category_id=?, name=?, name_ar=?, name_fr=?, description=?, price=?, old_price=?, image_url=?, sizes=?, is_active=? WHERE id=?')
                   ->execute([$catId, $name, $nameAr ?: null, $nameFr ?: null, $desc, $price, $oldPrice, $imageUrl, $sizes, $isActive, $id]);
                flash('success', 'Product updated!');
            }
            header('Location: /vestia_backend/vestia/admin/products.php'); exit;
        }
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $db->prepare('UPDATE products SET is_active=0 WHERE id=?')->execute([$id]);
        flash('success', 'Product removed.');
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
// ✅ ILIKE بدلاً من LIKE
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

// ✅ علامات اقتباس مفردة بدلاً من المزدوجة
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

          <!-- ✅ إصلاح 4 — حقل الاسم بالعربية -->
          <div class="mb-3">
            <label class="form-label">الاسم بالعربية <span class="text-muted" style="font-size:12px">(اختياري)</span></label>
            <input type="text" name="name_ar" class="form-control" dir="rtl"
                   placeholder="مثال: فستان صيفي"
                   value="<?= htmlspecialchars($editProduct['name_ar'] ?? '') ?>">
          </div>

          <!-- ✅ إصلاح 4 — حقل الاسم بالفرنسية -->
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

          <!-- ── Image Upload ── -->
          <div class="mb-3">
            <label class="form-label">Product Image</label>
            <div class="img-upload-area" id="uploadArea">
              <input type="file" name="image_file" id="imageFileInput"
                     accept="image/*"
                     onchange="handleImageSelect(this)">
              <div class="img-upload-placeholder" id="uploadPlaceholder">
                <span class="icon"><i class="bi bi-phone"></i></span>
                <span class="label">اضغط لاختيار صورة</span>
                <span class="hint">من المعرض أو التقاط صورة جديدة</span>
              </div>
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
          <button type="submit" class="btn btn-dark w-100">
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
                    <img src="<?= htmlspecialchars($p['image_url']) ?>" class="product-thumb" alt="">
                  <?php else: ?>
                    <div class="product-thumb-placeholder"><i class="bi bi-image"></i></div>
                  <?php endif; ?>
                  <div>
                    <div class="fw-600" style="font-size:13px"><?= htmlspecialchars($p['name']) ?></div>
                    <div style="font-size:11px;color:#9ca3af">#<?= $p['id'] ?></div>
                  </div>
                </div>
              </td>
              <!-- ✅ إصلاح 4 — عرض الأسماء المترجمة في الجدول -->
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
document.addEventListener('DOMContentLoaded', function () {
  const existing = document.getElementById('currentImageUrl').value;
  if (existing) showPreview(existing);
});

function handleImageSelect(input) {
  if (!input.files || !input.files[0]) return;
  const reader = new FileReader();
  reader.onload = e => showPreview(e.target.result);
  reader.readAsDataURL(input.files[0]);
}

function showPreview(src) {
  document.getElementById('previewImg').src = src;
  document.getElementById('uploadPlaceholder').style.display = 'none';
  document.getElementById('uploadPreview').style.display    = 'block';
}

function removeImage(e) {
  e.stopPropagation();
  document.getElementById('imageFileInput').value    = '';
  document.getElementById('currentImageUrl').value   = '';
  document.getElementById('previewImg').src          = '';
  document.getElementById('uploadPreview').style.display    = 'none';
  document.getElementById('uploadPlaceholder').style.display = 'flex';
}

function triggerPicker(e) {
  e.stopPropagation();
  document.getElementById('imageFileInput').click();
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
