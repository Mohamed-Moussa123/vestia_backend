<?php
// ============================================================
// VESTIA API — Category Controller
// ============================================================
class CategoryController {
    public static function index(): void {
        $db   = getDB();
        $lang = $_GET['lang'] ?? 'en'; // ✅ إصلاح 4 — دعم اللغة
        // ✅ إصلاح 4 — جلب name_ar و name_fr
        $stmt = $db->query('SELECT id, name, name_ar, name_fr, slug FROM categories ORDER BY sort_order ASC');
        $rows = $stmt->fetchAll();
        // ✅ إصلاح 4 — إرجاع الاسم حسب اللغة مع fallback للإنجليزية
        $categories = array_map(function($c) use ($lang) {
            $localizedName = match($lang) {
                'ar'    => $c['name_ar'] ?: $c['name'],
                'fr'    => $c['name_fr'] ?: $c['name'],
                default => $c['name'],
            };
            return [
                'id'   => (int)$c['id'],
                'name' => $localizedName,
                'slug' => $c['slug'],
            ];
        }, $rows);
        jsonSuccess(['categories' => $categories]);
    }
}
