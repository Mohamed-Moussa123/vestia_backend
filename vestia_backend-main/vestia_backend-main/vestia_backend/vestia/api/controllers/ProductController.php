<?php
// ============================================================
// VESTIA API — Product Controller
// ============================================================
class ProductController {

    public static function index(): void {
        $db = getDB();

        $categoryId = isset($_GET['category_id']) ? (int)$_GET['category_id'] : null;
        $search     = $_GET['search']   ?? null;
        $lang       = $_GET['lang']     ?? 'en'; // ✅ إصلاح 4 — دعم اللغة
        $page       = max(1, (int)($_GET['page']  ?? 1));
        $limit      = min(50, max(1, (int)($_GET['limit'] ?? 20)));
        $offset     = ($page - 1) * $limit;

        $where  = ['p.is_active = 1'];
        $params = [];

        if ($categoryId) {
            $where[]  = 'p.category_id = ?';
            $params[] = $categoryId;
        }

        if ($search) {
            $where[]  = 'p.name ILIKE ?';
            $params[] = '%' . $search . '%';
        }

        $whereSQL = implode(' AND ', $where);

        // ✅ إصلاح 4 — إضافة name_ar و name_fr للمنتجات والفئات
        $sql = "SELECT p.id, p.name, p.name_ar, p.name_fr, p.description, p.price, p.old_price,
                       p.image_url, p.sizes, p.created_at,
                       c.id AS category_id, c.name AS category_name,
                       c.name_ar AS category_name_ar, c.name_fr AS category_name_fr,
                       c.slug AS category_slug,
                       COALESCE(AVG(r.rating), 0) AS avg_rating,
                       COUNT(r.id) AS review_count
                FROM products p
                LEFT JOIN categories c ON c.id = p.category_id
                LEFT JOIN reviews    r ON r.product_id = p.id
                WHERE {$whereSQL}
                GROUP BY p.id, p.name, p.name_ar, p.name_fr, p.description, p.price, p.old_price,
                         p.image_url, p.sizes, p.created_at,
                         c.id, c.name, c.name_ar, c.name_fr, c.slug
                ORDER BY p.created_at DESC
                LIMIT {$limit} OFFSET {$offset}";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $products = $stmt->fetchAll();

        // Total count
        $countSql  = "SELECT COUNT(*) FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE {$whereSQL}";
        $countStmt = $db->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        jsonSuccess([
            'products'    => array_map(fn($p) => self::format($p, $lang), $products),
            'total'       => $total,
            'page'        => $page,
            'limit'       => $limit,
            'total_pages' => (int)ceil($total / $limit),
        ]);
    }

    public static function show(string $id): void {
        $db   = getDB();
        $lang = $_GET['lang'] ?? 'en'; // ✅ إصلاح 4 — دعم اللغة

        $stmt = $db->prepare(
            "SELECT p.*, p.name_ar, p.name_fr,
                    c.name AS category_name, c.name_ar AS category_name_ar,
                    c.name_fr AS category_name_fr, c.slug AS category_slug,
                    COALESCE(AVG(r.rating), 0) AS avg_rating,
                    COUNT(r.id) AS review_count
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             LEFT JOIN reviews    r ON r.product_id = p.id
             WHERE p.id = ? AND p.is_active = 1
             GROUP BY p.id, p.name, p.name_ar, p.name_fr, p.description, p.price, p.old_price,
                      p.image_url, p.sizes, p.created_at, p.is_active,
                      c.id, c.name, c.name_ar, c.name_fr, c.slug"
        );
        $stmt->execute([$id]);
        $product = $stmt->fetch();

        if (!$product) jsonError('Product not found', 404);

        jsonSuccess(['product' => self::format($product, $lang)]);
    }

    // ✅ إصلاح 3 + إصلاح 4 — format() محدّث بالكامل
    private static function format(array $p, string $lang = 'en'): array {
        $discount = null;
        if ($p['old_price'] && $p['old_price'] > $p['price']) {
            $discount = round((($p['old_price'] - $p['price']) / $p['old_price']) * 100);
        }

        // ✅ إصلاح 3 — تحويل المسار المحلي إلى رابط كامل
        $imageUrl = fixImageUrl($p['image_url']);

        // ✅ إصلاح 4 — اسم المنتج حسب اللغة
        $localizedName = match($lang) {
            'ar'    => $p['name_ar'] ?: $p['name'],
            'fr'    => $p['name_fr'] ?: $p['name'],
            default => $p['name'],
        };

        // ✅ إصلاح 4 — اسم الفئة حسب اللغة
        $localizedCategory = isset($p['category_id']) ? [
            'id'   => (int)$p['category_id'],
            'name' => match($lang) {
                'ar'    => $p['category_name_ar'] ?: $p['category_name'],
                'fr'    => $p['category_name_fr'] ?: $p['category_name'],
                default => $p['category_name'],
            },
            'slug' => $p['category_slug'],
        ] : null;

        return [
            'id'               => (int)$p['id'],
            'name'             => $localizedName,
            'description'      => $p['description'],
            'price'            => (float)$p['price'],
            'old_price'        => $p['old_price'] ? (float)$p['old_price'] : null,
            'discount_percent' => $discount,
            'image_url'        => $imageUrl,
            'sizes'            => explode(',', $p['sizes'] ?? 'S,M,L,XL,XXL'),
            'category'         => $localizedCategory,
            'avg_rating'       => round((float)$p['avg_rating'], 1),
            'review_count'     => (int)$p['review_count'],
            'created_at'       => $p['created_at'],
        ];
    }
}
