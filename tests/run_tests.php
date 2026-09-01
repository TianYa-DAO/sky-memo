<?php
// CLI: php tests/run_tests.php
error_reporting(E_ALL);
define('APP_TEST', true);
require __DIR__ . '/../config.php';
require __DIR__ . '/../app/db.php';
require __DIR__ . '/../app/functions.php';
require __DIR__ . '/../app/bosses.php';
require __DIR__ . '/../app/orders.php';
require __DIR__ . '/../app/records.php';
require __DIR__ . '/../app/auth.php';

$tests = [];
function check(string $name, bool $cond, string $detail = '') {
    global $tests;
    $tests[] = [$name, $cond, $detail];
    echo ($cond ? 'PASS ' : 'FAIL ') . $name . ($cond ? '' : "  -- $detail") . "\n";
}

// —— Task 3: functions ——
check('mon=1', weekday_matches('2026-09-07', [1,2,3,4,5]));       // 2026-09-07 是周一
check('sun=0', !weekday_matches('2026-09-06', [1,2,3,4,5]));      // 2026-09-06 是周日
check('in range', date_in_period('2026-09-10', '2026-09-01', '2026-09-19'));
check('out range', !date_in_period('2026-08-30', '2026-09-01', '2026-09-19'));
check('weekday_of', weekday_of('2026-09-07') === 1);
$enc = enc_secret('acc001');
check('enc_not_plain', $enc !== 'acc001' && strpos($enc, 'acc001') === false);
check('dec_roundtrip', dec_secret($enc) === 'acc001');

// —— Task 4-7: 数据库测试（连不上则跳过） ——
$dbOk = true;
try { get_pdo(); } catch (Exception $e) { $dbOk = false; echo "SKIP db tests: {$e->getMessage()}\n"; }

if ($dbOk && defined('APP_TEST')) {
    $pdo = get_pdo();

    // ---- bosses ----
    $pdo->exec("DELETE FROM bosses WHERE name='测试老板'");
    $id = boss_create($pdo, '测试老板', 'GID-001', 'acc001', 'pwd001', '测试备注');
    $b = boss_get($pdo, $id);
    check('boss_create', $b !== false);
    check('boss_enc', $b['account'] !== 'acc001' && strpos($b['account'], 'acc001') === false);
    check('boss_dec', dec_secret($b['account']) === 'acc001' && dec_secret($b['password']) === 'pwd001');
    check('boss_update', boss_update($pdo, $id, '测试老板2', 'GID-002', 'acc002', 'pwd002', 'x') && boss_get($pdo, $id)['name'] === '测试老板2');
    check('boss_delete', boss_delete($pdo, $id) && boss_get($pdo, $id) === null);

    // ---- orders ----
    $pdo->exec("DELETE FROM bosses WHERE name='订单测试老板'");
    $pdo->exec("DELETE FROM orders WHERE order_no LIKE 'SKY-TEST-%'");
    $bid = boss_create($pdo, '订单测试老板', 'G', 'a', 'p', '');
    // 生产环境可能有真实订单，待办断言用相对增量（基线+1/基线不变）
    $baseMon = count(today_todos($pdo, '2026-09-07'));
    $baseSun = count(today_todos($pdo, '2026-09-06'));
    $oid = order_create($pdo, $bid, ['包任务', '10蜡烛'], 'daily', '1,2,3,4,5', '2026-09-01', '2026-09-19', 300.00, '进行中', '测试周期单');
    $o = order_get($pdo, $oid);
    check('order_create', $o !== false);
    check('order_no_prefix', str_starts_with($o['order_no'], 'SKY-TEST-'));
    check('active_on_mon', active_on($o, '2026-09-07'));   // 周一，范围内
    check('not_weekend', !active_on($o, '2026-09-06'));    // 周日不在 weekdays
    check('not_started', !active_on($o, '2026-08-30'));    // 周期未开始
    check('done_inactive', !active_on(array_merge($o, ['status' => '已完成']), '2026-09-07'));
    check('multi_types', strpos($o['types'], '10蜡烛') !== false && strpos($o['types'], '包任务') !== false);

    // 指定日期模式（repeat_weekly=0）
    $oid2 = order_create($pdo, $bid, ['献祭'], 'once', '', '2026-09-01', '2026-09-30', 50, '进行中', '指定日期测试', 0, ['2026-09-05', '2026-09-12']);
    $o2 = order_get($pdo, $oid2);
    check('sched_dates_on', active_on($o2, '2026-09-05'));
    check('sched_dates_off', !active_on($o2, '2026-09-06'));
    check('sched_weekday_ignored', !active_on($o2, '2026-09-11')); // 周五但不在指定列表
    order_delete($pdo, $oid2);
    check('today_todos_find', count(today_todos($pdo, '2026-09-07')) === $baseMon + 1);
    check('today_todos_sun', count(today_todos($pdo, '2026-09-06')) === $baseSun);

    $tok = ensure_client_token($pdo, $oid);
    check('token_32', strlen($tok) === 32);
    check('token_link', order_by_token($pdo, $tok) !== null);
    check('bad_token', order_by_token($pdo, str_repeat('0', 32)) === null);

    // ---- records ----
    mark_order_done($pdo, $oid, '2026-09-08', true);
    check('mark_done', record_today_done($pdo, $oid, '2026-09-08') === true);
    add_total_record($pdo, '2026-09-08', 5, 2, 3, '测试总账');
    $sum = day_summary($pdo, '2026-09-08');
    check('day_sum_fig', (int)$sum['figure_count'] >= 0 + 5);  // order now has 0 figures + total record has 5 figures
    check('day_sum_cur', (int)$sum['currency_count'] === 0 + 3);  // order now has 0 + total has 3
    mark_order_done($pdo, $oid, '2026-09-08', false);
    check('mark_undo', record_today_done($pdo, $oid, '2026-09-08') === false);
    $cal = month_calendar($pdo, '2026-09');
    check('calendar_has_day', isset($cal['2026-09-08']));
    check('calendar_active', $cal['2026-09-07']['active_orders'] >= 1);

    // ---- orders: update/status ----
    check('order_update_ok', order_update($pdo, $oid, [
        'boss_id' => $bid, 'type' => '代币', 'daily_figure' => 0, 'daily_task' => 1, 'daily_currency' => 3,
        'weekdays' => '1,2,3,4,5', 'start_date' => '2026-09-01', 'end_date' => '2026-09-19',
        'price' => 200, 'payment_status' => '已付', 'status' => '进行中', 'notes' => '改'
    ]));
    check('order_change_status', order_change_status($pdo, $oid, '已完成', '已付') && order_get($pdo, $oid)['status'] === '已完成');

    // ---- auth ----
    $pdo->exec("DELETE FROM users WHERE username='testadmin'");
    admin_create($pdo, 'testadmin', 'abc123');
    check('admin_login_ok', admin_login($pdo, 'testadmin', 'abc123') !== false);
    check('admin_login_bad', admin_login($pdo, 'testadmin', 'wrong') === null);
    $pdo->exec("DELETE FROM users WHERE username='testadmin'");

    // ---- cleanup（只清理测试产生的数据，绝不碰生产数据）----
    $pdo->exec("DELETE FROM daily_records WHERE order_id IN ($oid,$oid2)");
    $pdo->exec("DELETE FROM client_views WHERE order_id IN ($oid,$oid2)");
    order_delete($pdo, $oid);
    $pdo->exec("DELETE FROM bosses WHERE id=$bid");
    $pdo->exec("DELETE FROM daily_records WHERE note LIKE '测试%' OR note='改'");
}

// 汇总
$fails = array_filter($tests, fn($t) => !$t[1]);
echo "\n" . (count($tests) - count($fails)) . '/' . count($tests) . " passed\n";
exit(count($fails) > 0 ? 1 : 0);