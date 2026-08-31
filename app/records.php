<?php
// 每日记录：勾选/汇总/月历
// 注意：依赖 orders.php 的 order_get/order_list，由调用方先 require orders.php（避免循环 require）
require_once __DIR__ . '/functions.php';

function record_today_done(PDO $pdo, int $orderId, string $date): bool {
    $st = $pdo->prepare("SELECT COUNT(*) c FROM daily_records WHERE order_id=? AND record_date=?");
    $st->execute([$orderId, $date]);
    return (int)$st->fetchColumn() > 0;
}

// 勾选/取消"今日已完成"：记录存在即视为已完成
function mark_order_done(PDO $pdo, int $orderId, string $date, bool $done): void {
    $existing = record_today_done($pdo, $orderId, $date);
    if ($done && !$existing) {
        $st = $pdo->prepare("INSERT INTO daily_records (record_date, order_id, figure_count, task_count, currency_count, note) VALUES (?,?,?,?,?,?)");
        $o = order_get($pdo, $orderId);
        $st->execute([$date, $orderId, (int)($o['daily_figure'] ?? 0), (int)($o['daily_task'] ?? 0), (int)($o['daily_currency'] ?? 0), '']);
    } elseif (!$done && $existing) {
        $st = $pdo->prepare("DELETE FROM daily_records WHERE order_id=? AND record_date=?");
        $st->execute([$orderId, $date]);
    }
}

// 总账行（order_id=NULL）
function add_total_record(PDO $pdo, string $date, int $fig, int $task, int $curr, string $note): void {
    $st = $pdo->prepare("INSERT INTO daily_records (record_date, order_id, figure_count, task_count, currency_count, note)
        VALUES (?,NULL,?,?,?,?) ON DUPLICATE KEY UPDATE figure_count=VALUES(figure_count), task_count=VALUES(task_count), currency_count=VALUES(currency_count), note=VALUES(note)");
    $st->execute([$date, $fig, $task, $curr, $note]);
}

// 某日汇总
function day_summary(PDO $pdo, string $date): array {
    $st = $pdo->prepare("SELECT COALESCE(SUM(figure_count),0) figure_count, COALESCE(SUM(task_count),0) task_count, COALESCE(SUM(currency_count),0) currency_count, COUNT(*) cnt FROM daily_records WHERE record_date=?");
    $st->execute([$date]);
    return $st->fetch();
}

// 月历数据：某月每天的汇总 + 活跃订单数
function month_calendar(PDO $pdo, string $ym): array {
    $start = $ym . '-01';
    $end = date('Y-m-t', strtotime($start));
    $days = [];
    $d = $start;
    while ($d <= $end) {
        $days[$d] = day_summary($pdo, $d);
        $days[$d]['active_orders'] = count(today_todos($pdo, $d));
        $d = date('Y-m-d', strtotime($d . ' +1 day'));
    }
    return $days;
}