<?php
/**
 * Helper MASK SĐT — dùng cho export nhật ký.
 *
 * Mask các trường SĐT (parent_phone, phone, father_phone, mother_phone, sdt)
 * trong cấu trúc mảng lồng nhau (kể cả diff_json before/after).
 *
 * Quy ước: giữ 4 đầu + '***' + 3 cuối. Chuỗi ngắn < 7 ký tự → '***' luôn.
 */

declare(strict_types=1);

namespace TNTT;

class LogMask
{
    private const PHONE_KEYS = [
        'parent_phone', 'phone', 'father_phone', 'mother_phone', 'sdt',
    ];

    /**
     * Đệ quy mask SĐT trong mọi giá trị là mảng/scalars.
     * Quy tắc:
     *  - key nằm trong PHONE_KEYS → mask
     *  - chuỗi toàn số 9-12 chữ số (không nằm trong key) → mask
     *  - các trường khác giữ nguyên
     */
    public static function phoneRecursive(mixed $val): mixed
    {
        if (is_array($val)) {
            $out = [];
            foreach ($val as $k => $v) {
                $key = is_string($k) ? strtolower($k) : '';
                if (in_array($key, self::PHONE_KEYS, true)) {
                    $out[$k] = self::maskValue($v);
                } else {
                    $out[$k] = self::phoneRecursive($v);
                }
            }
            return $out;
        }
        if (is_string($val) && preg_match('/^\d{9,12}$/', $val)) {
            return self::maskValue($val);
        }
        return $val;
    }

    public static function maskValue(mixed $v): string
    {
        $s = (string) $v;
        if (strlen($s) < 7) return '***';
        return substr($s, 0, 4) . '***' . substr($s, -3);
    }

    /**
     * Mask SĐT trong một string đơn lẻ (dùng cho what/detail không parse được JSON).
     * Tìm chuỗi 9-12 số liên tiếp và mask chúng.
     */
    public static function maskString(string $s): string
    {
        return preg_replace_callback(
            '/\b(\d{9,12})\b/',
            fn(array $m) => self::maskValue($m[1]),
            $s
        ) ?? $s;
    }
}
