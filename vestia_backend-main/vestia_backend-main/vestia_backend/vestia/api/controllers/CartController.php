<?php
// ============================================================
// VESTIA API — Cart Controller
// ============================================================
class CartController {

    /**
     * Helper method to get formatted cart items
     * Fixes image URLs and returns cart summary
     */
    private static function getCartData($userId): array {
        $db = getDB();
        
        $stmt = $db->prepare(
            "SELECT c.id, c.quantity, c.size,
                    p.id AS product_id, p.name, p.price, p.old_price, p.image_url
             FROM cart_items c
             JOIN products p ON p.id = c.product_id
             WHERE c.user_id = ? AND p.is_active = 1
             ORDER BY c.created_at DESC"
        );
        $stmt->execute([$userId]);
        $items = $stmt->fetchAll();

        // ✅ FIX IMAGE URLs
        $items = array_map(function($item) {
            $imageUrl = $item['image_url'];
            if ($imageUrl && strpos($imageUrl, 'http') !== 0 && strpos($imageUrl, '/') === 0) {
                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'];
                $item['image_url'] = "{$protocol}://{$host}{$imageUrl}";
            }
            return $item;
        }, $items);

        $subtotal    = array_sum(array_map(fn($i) => $i['price'] * $i['quantity'], $items));
        $shippingFee = count($items) > 0 ? SHIPPING_FEE : 0;

        return [
            'items'        => $items,
            'subtotal'     => round($subtotal, 2),
            'shipping_fee' => $shippingFee,
            'vat'          => 0,
            'total'        => round($subtotal + $shippingFee, 2),
            'item_count'   => array_sum(array_column($items, 'quantity')),
        ];
    }

    public static function index(): void {
        $user = getAuthUser();
        jsonSuccess(self::getCartData($user['id']));
    }

    public static function add(): void {
        $user = getAuthUser();
        $body = getRequestBody();

        $productId = (int)($body['product_id'] ?? 0);
        $quantity  = max(1, (int)($body['quantity'] ?? 1));
        $size      = strtoupper(sanitize($body['size'] ?? 'M'));

        if (!$productId) jsonError('product_id is required', 422);

        $db = getDB();

        $check = $db->prepare('SELECT id FROM products WHERE id = ? AND is_active = 1');
        $check->execute([$productId]);
        if (!$check->fetch()) jsonError('Product not found', 404);

        // ✅ ON CONFLICT بدلاً من ON DUPLICATE KEY UPDATE (PostgreSQL)
        // يتطلب وجود UNIQUE constraint على (user_id, product_id, size) في جدول cart_items
        $db->prepare(
            "INSERT INTO cart_items (user_id, product_id, quantity, size)
             VALUES (?, ?, ?, ?)
             ON CONFLICT (user_id, product_id, size)
             DO UPDATE SET quantity = cart_items.quantity + EXCLUDED.quantity"
        )->execute([$user['id'], $productId, $quantity, $size]);

        // ✅ RETURN UPDATED CART IMMEDIATELY - no need to refresh
        $cartData = self::getCartData($user['id']);
        jsonSuccess($cartData, 'Added to cart', 201);
    }

    public static function update(?string $id): void {
        $user = getAuthUser();
        if (!$id) jsonError('Cart item ID required', 422);

        $body     = getRequestBody();
        $quantity = (int)($body['quantity'] ?? 0);
        $db       = getDB();

        if ($quantity <= 0) {
            $db->prepare('DELETE FROM cart_items WHERE id = ? AND user_id = ?')
               ->execute([$id, $user['id']]);
        } else {
            $db->prepare('UPDATE cart_items SET quantity = ? WHERE id = ? AND user_id = ?')
               ->execute([$quantity, $id, $user['id']]);
        }

        // ✅ RETURN UPDATED CART
        $cartData = self::getCartData($user['id']);
        jsonSuccess($cartData, $quantity <= 0 ? 'Item removed' : 'Cart updated');
    }

    public static function remove(?string $id): void {
        $user = getAuthUser();
        if (!$id) jsonError('Cart item ID required', 422);

        $db = getDB();
        $db->prepare('DELETE FROM cart_items WHERE id = ? AND user_id = ?')
           ->execute([$id, $user['id']]);

        // ✅ RETURN UPDATED CART
        $cartData = self::getCartData($user['id']);
        jsonSuccess($cartData, 'Item removed from cart');
    }
}
