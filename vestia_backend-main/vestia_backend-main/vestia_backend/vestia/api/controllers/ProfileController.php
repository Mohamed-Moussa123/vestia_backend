<?php
// ============================================================
// VESTIA API — Profile Controller
// ============================================================
class ProfileController {
    public static function show(): void {
        $user = getAuthUser();
        $db   = getDB();
        // ✅ جلب phone بدلاً من email
        $stmt = $db->prepare('SELECT id, name, phone, created_at FROM users WHERE id = ?');
        $stmt->execute([$user['id']]);
        $profile = $stmt->fetch();
        if (!$profile) jsonError('User not found', 404);
        jsonSuccess(['user' => $profile]);
    }

    public static function update(): void {
        $user = getAuthUser();
        $body = getRequestBody();
        $db   = getDB();

        $name  = trim($body['name']  ?? '');
        $phone = trim($body['phone'] ?? '');
        $pass  = $body['password']   ?? '';

        // ── Validation ──
        $errors = [];
        if ($phone && !preg_match('/^\+?[0-9]{8,15}$/', $phone)) {
            $errors['phone'] = 'Invalid phone number';
        }
        if ($pass && strlen($pass) < 6) {
            $errors['password'] = 'Password must be 6+ characters';
        }
        if (!empty($errors)) jsonError('Validation failed', 422, $errors);

        $fields = [];
        $params = [];

        if ($name) {
            $fields[] = 'name = ?';
            $params[] = $name;
        }

        if ($phone) {
            // التحقق أن رقم الهاتف غير مستخدم من مستخدم آخر
            $dup = $db->prepare('SELECT id FROM users WHERE phone = ? AND id != ?');
            $dup->execute([$phone, $user['id']]);
            if ($dup->fetch()) jsonError('Phone number already in use', 409);

            $fields[] = 'phone = ?';
            $params[] = $phone;
        }

        if ($pass) {
            $fields[] = 'password = ?';
            $params[] = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);
        }

        if (empty($fields)) jsonError('Nothing to update', 422);

        $params[] = $user['id'];
        $db->prepare('UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?')
           ->execute($params);

        jsonSuccess([], 'Profile updated successfully');
    }
}
