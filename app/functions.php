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
function dec_secret(?string $cipher): string {
    if ($cipher === null || $cipher === '') return '';
    $raw = base64_decode($cipher, true);
    if ($raw === false || strlen($raw) < 17) return '';
    $iv = substr($raw, 0, 16);
    $dec = openssl_decrypt(substr($raw, 16), 'aes-256-cbc', AES_KEY, OPENSSL_RAW_DATA, $iv);
    return $dec === false ? '' : $dec;
}

// HTML 转义辅助
function h($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// 光遇代跑任务类型（多选）——按价目表
function task_types(): array {
    return ['每日任务', '10蜡烛', '15蜡烛', '20蜡烛', '挂饭', '包季', '包毕业', '献祭', '试炼', '晨岛', '云野', '雨林', '霞谷', '暮土', '禁阁'];
}

// 解析订单 types：JSON 字符串/数组 -> 数组
function parse_types($types): array {
    if (is_array($types)) return array_values(array_filter(array_map('trim', $types)));
    if ($types === null || $types === '') return [];
    $d = json_decode((string)$types, true);
    return is_array($d) ? $d : preg_split('/[,，]/', (string)$types, -1, PREG_SPLIT_NO_EMPTY);
}

// 类型显示：数组/JSON -> '类型1、类型2'
function types_label($types): string {
    return implode('、', parse_types($types));
}

// 签名元素：蜡烛 SVG（$lit=true 点亮金色，否则灰色）
function candle_svg(bool $lit = false, int $size = 44): string {
    $flame = $lit
        ? '<g class="flame"><path d="M22 10c0-3-2.5-5-2.5-5S17 7 17 10a5 5 0 0 0 10 0c0-2-1-3-1-3s-1 1-1 3" fill="#f7b84b"/></g>'
        : '<g class="flame" fill="none" stroke="#8fa0bd" stroke-width="1.5" stroke-linecap="round"><path d="M22 11c0-2.5-2-4.2-2-4.2S18 8.5 18 11a4 4 0 0 0 8 0c0-1.6-.8-2.4-.8-2.4s-.8.8-.8 2.4"/></g>';
    $body_opacity = $lit ? '1' : '.45';
    return '<svg class="candle' . ($lit ? ' glow' : '') . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 44 44" aria-hidden="true">'
        . $flame
        . '<g class="candle-body" opacity="' . $body_opacity . '">'
        . '<rect x="16" y="15" width="12" height="20" rx="4" fill="url(#candleGrad' . ($lit ? 'L' : '') . ')"/>'
        . '<rect x="15" y="35" width="14" height="3" rx="1.5" fill="#8fa0bd" opacity=".7"/>'
        . '</g>'
        . '<defs><linearGradient id="candleGradL" x1="0" y1="0" x2="1" y2="0">'
        . '<stop offset="0%" stop-color="#f7b84b"/><stop offset="100%" stop-color="#e9a83a"/>'
        . '</linearGradient><linearGradient id="candleGrad" x1="0" y1="0" x2="1" y2="0">'
        . '<stop offset="0%" stop-color="#aeb8cc"/><stop offset="100%" stop-color="#7e8ba6"/>'
        . '</linearGradient></defs></svg>';
}