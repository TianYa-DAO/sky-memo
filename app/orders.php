<?php
// 订单 CRUD + 周期激活 + 今日待办 + 客户 token
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/bosses.php';
// 注意：不 require records.php 以避免循环（records.php 依赖本文件的 order_get）
// 需要在 records.php 的函数前先加载本文件；admin/public 已按顺序 require

function order_create(PDO $pdo, int $bossId, $types, string $period = 'weekly',
    string $weekdays = '1,2,3,4,5', string $start = '', string $end = '',
    float $price = 0, string $status = '进行中', string $notes = '',
    int $repeatWeekly = 1, $selectedDates = null, string $paymentStatus = '未付'): int {
    $orderNo = defined('APP_TEST') && APP_TEST
        ? 'SKY-TEST-' . substr((string)time(), -6) . '-' . random_int(10, 99)
        : generate_order_no($pdo);
    $typesJson = json_encode(parse_types($types), JSON_UNESCAPED_UNICODE);
    $selectedJson = json_encode(is_array($selectedDates) ? $selectedDates : (parse_types($selectedDates) ?: []), JSON_UNESCAPED_UNICODE);
    $primary = types_label($types) ?: ($typesJson === '[]' ? '代跑' : substr(parse_types($types)[0] ?? '代跑', 0, 16));
    $st = $pdo->prepare("INSERT INTO orders (order_no,boss_id,type,types,period,repeat_weekly,selected_dates,weekdays,start_date,end_date,price,payment_status,status,notes)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $st->execute([$orderNo,$bossId,$primary,$typesJson,$period,(int)$repeatWeekly,$selectedJson,
        '[' . $weekdays . ']', $start, $end, $price, $paymentStatus, $status, $notes]);
    $oid = (int)$pdo->lastInsertId();
    // 自动为订单生成客户查看 token
    ensure_client_token($pdo, $oid);
    return $oid;
}

function order_get(PDO $pdo, int $id): ?array {
    if ($id <= 0) return null;
    $st = $pdo->prepare("SELECT * FROM orders WHERE id=?");
    $st->execute([$id]);
    $row = $st->fetch();
    return $row === false ? null : $row;
}

function order_list(PDO $pdo, ?string $status = null): array {
    if ($status === null) return $pdo->query("SELECT * FROM orders ORDER BY created_at DESC")->fetchAll();
    $st = $pdo->prepare("SELECT * FROM orders WHERE status=? ORDER BY created_at DESC");
    $st->execute([$status]);
    return $st->fetchAll();
}

function order_update(PDO $pdo, int $id, array $data): bool {
    $typesJson = json_encode(parse_types($data['types'] ?? []), JSON_UNESCAPED_UNICODE);
    $selectedJson = json_encode(is_array($data['selected_dates'] ?? null) ? $data['selected_dates'] : (parse_types($data['selected_dates'] ?? null) ?: []), JSON_UNESCAPED_UNICODE);
    $repeatWeekly = (int)($data['repeat_weekly'] ?? 1);
    $period = $data['period'] ?? 'weekly';
    $st = $pdo->prepare("UPDATE orders SET boss_id=:boss_id, types=:types, period=:period, repeat_weekly=:rw, selected_dates=:sd,
        weekdays=:wd, start_date=:sd2, end_date=:ed, price=:price, payment_status=:ps, status=:st, notes=:notes WHERE id=:id");
    $st->execute([
        ':boss_id' => $data['boss_id'], ':types' => $typesJson, ':period' => $period,
        ':rw' => $repeatWeekly, ':sd' => $selectedJson,
        ':wd' => '[' . ($data['weekdays'] ?? '1,2,3,4,5') . ']', ':sd2' => $data['start_date'], ':ed' => $data['end_date'],
        ':price' => (float)$data['price'], ':ps' => $data['payment_status'], ':st' => $data['status'],
        ':notes' => $data['notes'] ?? '', ':id' => $id,
    ]);
    return true;
}

function order_change_status(PDO $pdo, int $id, string $status, ?string $payment = null): bool {
    $sql = "UPDATE orders SET status=?";
    $args = [$status];
    if ($payment !== null) { $sql .= ", payment_status=?"; $args[] = $payment; }
    $sql .= " WHERE id=?"; $args[] = $id;
    $st = $pdo->prepare($sql);
    $st->execute($args);
    return true;
}

function order_delete(PDO $pdo, int $id): bool {
    $st = $pdo->prepare("DELETE FROM orders WHERE id=?");
    $st->execute([$id]);
    return $st->rowCount() > 0;
}

// 订单在指定日期是否激活（进行中 + 周期内 + 按周几或指定日期）
function active_on(array $order, string $date): bool {
    if (($order['status'] ?? '') !== '进行中') return false;
    if (!date_in_period($date, $order['start_date'], $order['end_date'])) return false;
    // 每周重复：按 weekdays；否则：按 selected_dates
    if ((int)($order['repeat_weekly'] ?? 1) === 1) {
        $wd = json_decode($order['weekdays'] ?? '[]', true) ?: [];
        return weekday_matches($date, $wd);
    }
    $dates = json_decode($order['selected_dates'] ?? '[]', true) ?: [];
    return in_array($date, $dates, true);
}

// 今日待办（含老板名、今日是否已完成）
function today_todos(PDO $pdo, string $date): array {
    $orders = order_list($pdo, '进行中');
    $out = [];
    foreach ($orders as $o) {
        if (active_on($o, $date)) {
            $boss = boss_get($pdo, (int)$o['boss_id']);
            $o['boss_name'] = $boss['name'] ?? '?';
            $o['done_today'] = record_today_done($pdo, (int)$o['id'], $date);
            $out[] = $o;
        }
    }
    return $out;
}

// 客户 token
function ensure_client_token(PDO $pdo, int $orderId): string {
    $st = $pdo->prepare("SELECT token FROM client_views WHERE order_id=?");
    $st->execute([$orderId]);
    $tok = $st->fetchColumn();
    if ($tok) return $tok;
    $tok = bin2hex(random_bytes(16));
    $st = $pdo->prepare("INSERT INTO client_views (order_id, token) VALUES (?,?)");
    $st->execute([$orderId, $tok]);
    return $tok;
}
function order_by_token(PDO $pdo, string $token): ?array {
    $st = $pdo->prepare("SELECT o.* FROM orders o JOIN client_views v ON v.order_id=o.id WHERE v.token=?");
    $st->execute([$token]);
    $row = $st->fetch();
    return $row === false ? null : $row;
}

// 取订单的客户进度 token（无则生成）
function order_token(PDO $pdo, int $orderId): string {
    return ensure_client_token($pdo, $orderId);
}