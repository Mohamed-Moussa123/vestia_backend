<?php
session_start();
require_once __DIR__ . '/includes/db.php';
adminCheck();
$db = db();

// Update status
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $id     = (int)$_POST['id'];
    $status = $_POST['status'] ?? '';
    $allowed = ['Packing','Picked','In Transit','Completed','Cancelled'];
    if ($id && in_array($status, $allowed)) {
        $db->prepare('UPDATE orders SET status=? WHERE id=?')->execute([$status,$id]);
        flash('success', 'Order #' . $id . ' status updated to ' . $status);
    }
    header('Location: /vestia_backend/vestia/admin/orders.php'); exit;
}

// Single order view
$viewOrder = null;
if (isset($_GET['id'])) {
    $stmt = $db->prepare('SELECT o.*,u.name AS user_name,u.phone FROM orders o JOIN users u ON u.id=o.user_id WHERE o.id=?');
    $stmt->execute([(int)$_GET['id']]);
    $viewOrder = $stmt->fetch();
    if ($viewOrder) {
        $items = $db->prepare('SELECT * FROM order_items WHERE order_id=?');
        $items->execute([$viewOrder['id']]);
        $viewOrder['items'] = $items->fetchAll();
    }
}

// List with filters
$statusFilt = $_GET['status'] ?? '';
$search     = trim($_GET['search'] ?? '');
$page       = max(1,(int)($_GET['page']??1));
$limit      = 15;
$offset     = ($page-1)*$limit;

$where  = ['1=1'];
$params = [];
if ($statusFilt) { $where[] = 'o.status=?'; $params[] = $statusFilt; }
// ✅ ILIKE بدلاً من LIKE (PostgreSQL حساس لحالة الأحرف)، وCAST للـ id
if ($search)     { $where[] = '(u.name ILIKE ? OR u.phone ILIKE ? OR o.id=?)'; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = (int)$search; }
$whereSQL = implode(' AND ',$where);

$totalStmt = $db->prepare("SELECT COUNT(*) FROM orders o JOIN users u ON u.id=o.user_id WHERE $whereSQL");
$totalStmt->execute($params);
$total = (int)$totalStmt->fetchColumn();
$pages = max(1,(int)ceil($total/$limit));

// ✅ LIMIT و OFFSET كقيم مباشرة (PostgreSQL لا يقبلهما كـ parameters في بعض الإصدارات)
$stmt = $db->prepare("SELECT o.*,u.name AS user_name,u.phone FROM orders o JOIN users u ON u.id=o.user_id WHERE $whereSQL ORDER BY o.created_at DESC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$orders = $stmt->fetchAll();

$pageTitle = 'Orders';
include __DIR__ . '/includes/header.php';
?>

<?php $succ=flash('success'); if($succ): ?><div class="alert alert-success" data-auto-dismiss><?= htmlspecialchars($succ) ?></div><?php endif; ?>

<?php if ($viewOrder): ?>
<!-- Single Order Detail View -->
<div class="d-flex align-items-center gap-3 mb-4">
  <a href="/vestia_backend/vestia/admin/orders.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to Orders</a>
  <h4 class="mb-0 fw-800">Order #<?= $viewOrder['id'] ?></h4>
  <?php $sc=['Packing'=>'bs-packing','Picked'=>'bs-picked','In Transit'=>'bs-transit','Completed'=>'bs-completed','Cancelled'=>'bs-cancelled'][$viewOrder['status']]??''; ?>
  <span class="badge-status <?= $sc ?>"><?= $viewOrder['status'] ?></span>
</div>

<div class="row g-4">
  <div class="col-lg-8">
    <div class="card mb-4">
      <div class="card-header"><h5><i class="bi bi-cart me-2"></i>Order Items</h5></div>
      <div class="table-responsive">
        <table class="table">
          <thead><tr><th>Product</th><th>Size</th><th>Price</th><th>Qty</th><th>Subtotal</th></tr></thead>
          <tbody>
          <?php foreach ($viewOrder['items'] as $item): ?>
            <tr>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <?php if($item['image_url']): ?><img src="<?= htmlspecialchars($item['image_url']) ?>" class="product-thumb" alt=""><?php endif; ?>
                  <span class="fw-600" style="font-size:13px"><?= htmlspecialchars($item['name']) ?></span>
                </div>
              </td>
              <td><?= htmlspecialchars($item['size']) ?></td>
              <td><?= formatPrice($item['price']) ?></td>
              <td><?= $item['quantity'] ?></td>
              <td class="fw-700"><?= formatPrice($item['price']*$item['quantity']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="p-3 border-top">
        <div class="d-flex justify-content-between mb-1"><span class="text-muted">Subtotal</span><span><?= formatPrice($viewOrder['subtotal']) ?></span></div>
        <div class="d-flex justify-content-between mb-1"><span class="text-muted">Shipping</span><span><?= formatPrice($viewOrder['shipping_fee']) ?></span></div>
        <div class="d-flex justify-content-between mb-1"><span class="text-muted">VAT</span><span><?= formatPrice($viewOrder['vat']) ?></span></div>
        <div class="d-flex justify-content-between mt-2 pt-2 border-top"><span class="fw-700">Total</span><span class="fw-800 fs-5"><?= formatPrice($viewOrder['total']) ?></span></div>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card mb-3">
      <div class="card-header"><h5><i class="bi bi-person me-2"></i>Customer</h5></div>
      <div class="p-3">
        <div class="fw-700"><?= htmlspecialchars($viewOrder['user_name']) ?></div>
        <div class="text-muted" style="font-size:13px"> 
          <i class="bi bi-telephone me-1"></i><?= htmlspecialchars($viewOrder['phone'] ?? 'N/A') ?>
        </div>
        <div class="text-muted mt-2" style="font-size:12px"><i class="bi bi-calendar me-1"></i><?= date('M j, Y H:i', strtotime($viewOrder['created_at'])) ?></div>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><h5><i class="bi bi-truck me-2"></i>Update Status</h5></div>
      <div class="p-3">
        <form method="POST">
          <input type="hidden" name="_csrf" value="<?= csrf() ?>">
          <input type="hidden" name="id" value="<?= $viewOrder['id'] ?>">
          <select name="status" class="form-select mb-3">
            <?php foreach(['Packing','Picked','In Transit','Completed','Cancelled'] as $s): ?>
              <option value="<?= $s ?>" <?= $viewOrder['status']===$s?'selected':'' ?>><?= $s ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn btn-dark w-100"><i class="bi bi-check2 me-2"></i>Update Status</button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php else: ?>
<!-- Orders List -->
<div class="card">
  <div class="card-header justify-content-between flex-wrap gap-2">
    <h5><i class="bi bi-box-seam me-2"></i>All Orders <span class="text-muted fw-normal" style="font-size:13px">(<?= $total ?>)</span></h5>
    <form class="d-flex gap-2" method="GET">
      <input type="text" name="search" class="form-control form-control-sm" placeholder="Order ID or customer..." value="<?= htmlspecialchars($search) ?>" style="width:200px">
      <select name="status" class="form-select form-select-sm" style="width:140px">
        <option value="">All Statuses</option>
        <?php foreach(['Packing','Picked','In Transit','Completed','Cancelled'] as $s): ?>
          <option value="<?= $s ?>" <?= $statusFilt===$s?'selected':'' ?>><?= $s ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-sm btn-dark">Filter</button>
      <?php if($search||$statusFilt): ?><a href="/vestia_backend/vestia/admin/orders.php" class="btn btn-sm btn-outline-secondary">Clear</a><?php endif; ?>
    </form>
  </div>
  <div class="table-responsive">
    <table class="table">
      <thead><tr><th>#ID</th><th>Customer</th><th>Items</th><th>Total</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($orders as $o):
        $sc=['Packing'=>'bs-packing','Picked'=>'bs-picked','In Transit'=>'bs-transit','Completed'=>'bs-completed','Cancelled'=>'bs-cancelled'][$o['status']]??'';
        $itemCount = $db->prepare('SELECT COALESCE(SUM(quantity),0) FROM order_items WHERE order_id=?');
        $itemCount->execute([$o['id']]);
        $cnt = (int)$itemCount->fetchColumn();
      ?>
        <tr>
          <td class="fw-700">#<?= $o['id'] ?></td>
          <td>
            <div class="fw-600" style="font-size:13px"><?= htmlspecialchars($o['user_name']) ?></div>
            <div style="font-size:11px;color:#9ca3af">
              <i class="bi bi-telephone me-1"></i><?= htmlspecialchars($o['phone'] ?? 'N/A') ?>
            </div>
          </td>
          <td style="font-size:13px"><?= $cnt ?> item<?= $cnt!=1?'s':'' ?></td>
          <td class="fw-700"><?= formatPrice($o['total']) ?></td>
          <td><span class="badge-status <?= $sc ?>"><?= $o['status'] ?></span></td>
          <td style="font-size:12px;color:#9ca3af"><?= timeAgo($o['created_at']) ?></td>
          <td><a href="/vestia_backend/vestia/admin/orders.php?id=<?= $o['id'] ?>" class="btn-icon"><i class="bi bi-eye"></i></a></td>
        </tr>
      <?php endforeach; ?>
      <?php if(empty($orders)): ?>
        <tr><td colspan="7" class="text-center text-muted py-4">No orders found</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pages > 1): ?>
  <div class="p-3 d-flex justify-content-center">
    <nav><ul class="pagination mb-0">
      <?php for($i=1;$i<=$pages;$i++): ?>
        <li class="page-item <?= $i==$page?'active':'' ?>">
          <a class="page-link" href="?page=<?= $i ?>&status=<?= urlencode($statusFilt) ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
        </li>
      <?php endfor; ?>
    </ul></nav>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
