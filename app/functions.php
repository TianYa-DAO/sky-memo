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

// 光遇代跑服务目录（按价目表）——含价格、描述、周期
function service_catalog(): array {
    return [
        // ---- 兼容旧数据：旧版/前台无勾选时的默认"代跑" ----
        '代跑'    => ['desc' => '综合代跑（默认服务，含每日任务）', 'daily' => 1, 'weekly' => 6, 'monthly' => 28, 'recurring' => true],
        // ---- 基础任务 ----
        '每日任务' => ['desc' => '每日4个任务，含亲密度', 'daily' => 1, 'weekly' => 6, 'monthly' => 28, 'recurring' => true],
        // ---- 蜡烛代跑（按蜡烛数量分档）----
        '10蜡烛' => ['desc' => '10根蜡烛/天，含每日+亲密度', 'daily' => 4, 'weekly' => 26, 'monthly' => 110, 'recurring' => true],
        '15蜡烛' => ['desc' => '15根蜡烛/天，送4❤️，含每日+代币', 'daily' => 6, 'weekly' => 40, 'monthly' => 170, 'recurring' => true],
        '20蜡烛' => ['desc' => '20根蜡烛/天，送5❤️，含每日+代币', 'daily' => 8, 'weekly' => 55, 'monthly' => 230, 'recurring' => true],
        // ---- 挂机 ----
        '挂饭'    => ['desc' => '挂机收烛，包月送代币+3❤️', 'daily' => 1, 'weekly' => 6.5, 'monthly' => 24, 'recurring' => true],
        // ---- 季度服务 ----
        '包季'    => ['desc' => '每日+亲密度+季节任务', 'once' => 100, 'recurring' => false],
        '包毕业'  => ['desc' => '赛季毕业（不含卡）', 'once' => null, 'recurring' => false],
        // ---- 一次性 ----
        '献祭'    => ['desc' => '暴风眼献祭，无翼不接', 'per_run' => 5, 'recurring' => false],
        '试炼'    => ['desc' => '各试炼地图，2r/图', 'per_map' => 2, 'recurring' => false],
        // ---- 地图代跑 ----
        '晨岛' => ['desc' => '晨岛全图跑烛', 'per_run' => 1.5, 'recurring' => false],
        '云野' => ['desc' => '云野全图跑烛', 'per_run' => 2, 'recurring' => false],
        '雨林' => ['desc' => '雨林全图跑烛', 'per_run' => 2, 'recurring' => false],
        '霞谷' => ['desc' => '霞谷全图跑烛', 'per_run' => 3, 'recurring' => false],
        '暮土' => ['desc' => '暮土全图跑烛', 'per_run' => 3, 'recurring' => false],
        '禁阁' => ['desc' => '禁阁全图跑烛', 'per_run' => 2, 'recurring' => false],
    ];
}

// 光遇代跑任务类型（多选）——按价目表（保留兼容）
function task_types(): array {
    return array_keys(service_catalog());
}

// 获取服务描述
function service_desc(string $type): string {
    $cat = service_catalog();
    return $cat[$type]['desc'] ?? '';
}

// 判断服务是否为循环服务（日/周/月）
function service_recurring(string $type): bool {
    $cat = service_catalog();
    return $cat[$type]['recurring'] ?? false;
}

// 根据服务类型 + 周期 + 服务数量自动计算价格
// $period: 'daily'/'weekly'/'monthly'/'once'（一次性）
// $count: 服务次数（地图数/献祭次数等一次性服务用，默认1）
function calculate_price(array $types, string $period = 'daily', int $count = 1): float {
    $catalog = service_catalog();
    $total = 0;
    foreach ($types as $t) {
        $svc = $catalog[$t] ?? null;
        if (!$svc) continue;
        if ($svc['recurring']) {
            $total += $svc[$period] ?? 0;
        } elseif ($period === 'once' && isset($svc['once'])) {
            $total += $svc['once'] * $count;
        } elseif (isset($svc['per_run'])) {
            $total += $svc['per_run'] * $count;
        } elseif (isset($svc['per_map'])) {
            $total += $svc['per_map'] * $count;
        }
    }
    return $total;
}

// 周期显示：'daily' -> '每天', 'weekly' => '每周', etc
function period_label(string $period): string {
    return ['daily' => '每天', 'weekly' => '每周', 'monthly' => '每月', 'once' => '一次'][ $period ] ?? $period;
}

// 输出服务目录 JSON（供前端 JS 自动定价）
function service_catalog_json(): string {
    $out = [];
    foreach (service_catalog() as $name => $svc) {
        $out[$name] = [
            'desc' => $svc['desc'] ?? '',
            'recurring' => $svc['recurring'] ?? false,
            'daily' => $svc['daily'] ?? null,
            'weekly' => $svc['weekly'] ?? null,
            'monthly' => $svc['monthly'] ?? null,
            'once' => $svc['once'] ?? null,
            'per_run' => $svc['per_run'] ?? null,
            'per_map' => $svc['per_map'] ?? null,
        ];
    }
    return json_encode($out, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
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