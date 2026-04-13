<?php
// ============================================================
// controllers/TryOnController.php
// ============================================================
// يتطلب مفتاح Replicate في ملف config/database.php
// أضف هذا السطر في config/database.php:
//   define('REPLICATE_API_TOKEN', 'r8_xxxxxxxxxxxxxxxxxxxxxxxx');
//
// احصل على مفتاحك المجاني من: https://replicate.com/account/api-tokens
// ============================================================

class TryOnController
{
    public static function generate(): void
    {
        // ── 1. التحقق من المصادقة ──────────────────────────────
        $user = requireAuth();

        // ── 2. التحقق من وجود البيانات المطلوبة ───────────────
        if (empty($_FILES['person_image']) || $_FILES['person_image']['error'] !== UPLOAD_ERR_OK) {
            jsonError('person_image is required', 400);
            return;
        }

        $garmentUrl = $_POST['garment_image_url'] ?? '';
        if (empty($garmentUrl) || !filter_var($garmentUrl, FILTER_VALIDATE_URL)) {
            jsonError('garment_image_url is required and must be a valid URL', 400);
            return;
        }

        // ── 3. التحقق من نوع الملف وحجمه ──────────────────────
        $file    = $_FILES['person_image'];
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        $mime    = mime_content_type($file['tmp_name']);

        if (!in_array($mime, $allowed)) {
            jsonError('Only JPEG, PNG, and WebP images are allowed', 400);
            return;
        }
        if ($file['size'] > 5 * 1024 * 1024) {
            jsonError('Image size must not exceed 5MB', 400);
            return;
        }

        // ── 4. رفع الصورة إلى مجلد مؤقت ──────────────────────
        $uploadDir = __DIR__ . '/../uploads/tryon_temp/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $ext      = $mime === 'image/png' ? 'png' : ($mime === 'image/webp' ? 'webp' : 'jpg');
        $filename = 'tryon_' . uniqid() . '_' . time() . '.' . $ext;
        $path     = $uploadDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $path)) {
            jsonError('Failed to process image', 500);
            return;
        }

        // بناء URL عام للصورة
        $proto     = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host      = $_SERVER['HTTP_HOST'];
        $base      = dirname($_SERVER['SCRIPT_NAME']);
        $personUrl = $proto . '://' . $host . $base . '/uploads/tryon_temp/' . $filename;

        // ── 5. استدعاء Replicate API ────────────────────────────
        $token = defined('REPLICATE_API_TOKEN') ? REPLICATE_API_TOKEN : '';
        if (empty($token)) {
            @unlink($path);
            jsonError('AI service not configured', 503);
            return;
        }

        $predictionId = self::createPrediction($token, $personUrl, $garmentUrl);
        if (!$predictionId) {
            @unlink($path);
            jsonError('Failed to start AI generation', 503);
            return;
        }

        // ── 6. انتظار النتيجة (max 90 ثانية) ───────────────────
        $resultUrl = self::pollPrediction($token, $predictionId);
        @unlink($path); // حذف الصورة المؤقتة دائماً

        if (!$resultUrl) {
            jsonError('AI generation timed out — please try again', 504);
            return;
        }

        // ── 7. إرجاع النتيجة ───────────────────────────────────
        jsonSuccess(['result_url' => $resultUrl]);
    }

    // ── إنشاء طلب جديد في Replicate ───────────────────────────
    private static function createPrediction(string $token, string $personUrl, string $garmentUrl): ?string
    {
        $payload = json_encode([
            // IDM-VTON — أدق نموذج Virtual Try-On مفتوح المصدر
            'version' => 'c871bb9b046607b680449ecbae55fd8c6d945e0a1948644bf2361b3d021d3ff4',
            'input'   => [
                'human_img'       => $personUrl,
                'garm_img'        => $garmentUrl,
                'garment_des'     => 'clothing item',
                'is_checked'      => true,
                'is_checked_crop' => false,
                'denoise_steps'   => 30,
                'seed'            => 42,
            ],
        ]);

        $ch = curl_init('https://api.replicate.com/v1/predictions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Token ' . $token,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 201 || !$response) return null;

        $data = json_decode($response, true);
        return $data['id'] ?? null;
    }

    // ── الانتظار حتى تنتهي المعالجة ───────────────────────────
    private static function pollPrediction(string $token, string $predictionId): ?string
    {
        $url         = 'https://api.replicate.com/v1/predictions/' . $predictionId;
        $maxAttempts = 30; // 30 × 3 ثوانٍ = 90 ثانية

        for ($i = 0; $i < $maxAttempts; $i++) {
            sleep(3);

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => ['Authorization: Token ' . $token],
                CURLOPT_TIMEOUT        => 15,
            ]);
            $response = curl_exec($ch);
            curl_close($ch);

            if (!$response) continue;

            $data   = json_decode($response, true);
            $status = $data['status'] ?? '';

            if ($status === 'succeeded') {
                // IDM-VTON يُرجع مصفوفة: [1] = الصورة المحسّنة، [0] = الأساسية
                $output = $data['output'] ?? null;
                if (is_array($output)) return $output[1] ?? $output[0] ?? null;
                return is_string($output) ? $output : null;
            }

            if ($status === 'failed' || $status === 'canceled') return null;
        }

        return null; // timeout
    }
}
