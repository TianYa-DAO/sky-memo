<?php
// 公共函数：日期/周期工具 + 加密

// 返回某日期是周几（1=周一 … 7=周日）
function weekday_of(string $date): int {
    return (int)date('N', strtotime($date));
}

// 判断日期是否匹配 weekdays 数组
function weekday_matches(string $date, array $weekdays): bool {
    return in_array(weekday_of($date), $weekdays, true);
}

// 判断日期是否在 [start, end] 区间内
function date_in_period(string $date, string $start, string $end): bool {
    return $date >= $start && $date <= $end;
}

// 生成订单号：SKY-YYYYMMDD-序号
function generate_order_no(PDO $pdo): string {
    $prefix = 'SKY-' . date('Ymd') . '-';
    $row = $pdo->query("SELECT COUNT(*) c FROM orders WHERE order_no LIKE '" . $prefix . "%'")->fetch();
    return $prefix . str_pad((int)($row['c'] ?? 0) + 1, 3, '0', STR_PAD_LEFT);
}

// AES-256-CBC 加密（AES_KEY 必须 32 字节）
function enc_secret(string $plain): string {
    $iv = random_bytes(16);
    return base64_encode($iv . openssl_encrypt($plain, 'aes-256-cbc', AES_KEY, OPENSSL_RAW_DATA, $iv));
}
function dec_secret(string $cipher): string {
    $raw = base64_decode($cipher);
    $iv = substr($raw, 0, 16);
    return openssl_decrypt(substr($raw, 16), 'aes-256-cbc', AES_KEY, OPENSSL_RAW_DATA, $iv);
}

// HTML 转义辅助
function h($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}