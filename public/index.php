<?php
// 前台入口：下单表单 + 客户进度页（?token=xxx）
require __DIR__ . '/../config.php';
require __DIR__ . '/../app/db.php';
require __DIR__ . '/../app/functions.php';
require __DIR__ . '/../app/auth.php';
require __DIR__ . '/../app/bosses.php';
require __DIR__ . '/../app/orders.php';
require __DIR__ . '/../app/records.php';

$pdo = get_pdo();
session_start();

/* ---------- 客户进度页 ---------- */
if (isset($_GET['token']) && $_GET['token'] !== '') {
    $order = order_by_token($pdo, $_GET['token']);
    if ($order === null) { http_response_code(404); header('Content-Type: text/html; charset=utf-8'); echo '<div class="container card"><h1>链接无效</h1><p><a href="index.php">← 返回首页</a></p></div>'; exit; }
    $boss = boss_get($pdo, (int)$order['boss_id']);
    // 累计
    $st = $pdo->prepare("SELECT COALESCE(SUM(figure_count),0) f, COALESCE(SUM(task_count),0) t, COALESCE(SUM(currency_count),0) c FROM daily_records WHERE order_id=?");
    $st->execute([(int)$order['id']]);
    $sum = $st->fetch();
    $statusText = ['待接' => '等待接单', '进行中' => '进行中', '已完成' => '已完成 ✅', '已取消' => '已取消'][$order['status']] ?? $order['status'];
    echo '<!doctype html><html lang="zh-CN"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>订单进度 - 光遇备忘录</title><link rel="stylesheet" href="assets/style.css"></head><body>';
    echo '<div class="container"><div class="card view-card">';
    echo '<div class="view-candle">' . candle_svg($order['status'] === '已完成', 56) . '</div>';
    echo '<h1>' . h($statusText) . '</h1>';
    echo '<p class="view-no">订单号：' . h($order['order_no']) . '</p>';
    echo '<div class="view-row"><span>类型</span><b>' . h(types_label($order['types'] ?? '')) . '</b></div>';
    echo '<div class="view-row"><span>周期</span><b>' . $order['start_date'] . ' ~ ' . $order['end_date'] . '</b></div>';
    echo '<div class="view-row"><span>每日约定</span><b>图×' . (int)$order['daily_figure'] . ' + 任务×' . (int)$order['daily_task'] . ' + 代币×' . (int)$order['daily_currency'] . '</b></div>';
    echo '<div class="view-row"><span>累计完成</span><b>图' . (int)$sum['f'] . ' / 任务' . (int)$sum['t'] . ' / 代币' . (int)$sum['c'] . '</b></div>';
    if ($boss && $boss['name']) echo '<div class="view-row"><span>接单老板</span><b>' . h($boss['name']) . '</b></div>';
    if ($order['notes']) echo '<div class="view-row"><span>备注</span><b>' . h($order['notes']) . '</b></div>';
    echo '<p class="view-tip">有问题请联系接单老板</p>';
    echo '</div></div></body></html>';
    exit;
}

/* ---------- 下单表单 ---------- */
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $info = trim($_POST['info'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    if ($info === '') { $msg = '请填写需求说明'; }
    else {
        // 客户提交：创建"待接"订单；类型多选；默认工作日、7天周期；老板取第一个可用（无则0占位）
        $typesPost = $_POST['types'] ?? [];
        $typesList = is_array($typesPost) ? array_values(array_filter(array_map('trim', $typesPost))) : [];
        if (empty($typesList)) $typesList = ['代跑'];
        $bossesList = boss_list($pdo);
        $newBossId = !empty($bossesList) ? (int)$bossesList[0]['id'] : 0;
        $oid = order_create($pdo, $newBossId, $typesList, 0, 0, 0, '1,2,3,4,5', date('Y-m-d'), date('Y-m-d', strtotime('+7 day')), 0, '待接', '客户提交: ' . $info . ($contact !== '' ? ' 联系方式:' . $contact : ''));
        $order = order_get($pdo, $oid);
        $tok = order_token($pdo, $oid);
        $msg = '提交成功！订单号：' . $order['order_no'] . '<br>进度链接（请保存）：<a href="index.php?token=' . h($tok) . '">index.php?token=' . h($tok) . '</a>，可随时查看进度';
    }
}
$bosses = boss_list($pdo);
echo '<!doctype html><html lang="zh-CN"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
echo '<title>光遇接单备忘录</title><link rel="stylesheet" href="assets/style.css"></head><body>';
echo '<div class="hero">' . candle_svg(true, 56) . '<h1>✨ 光遇接单</h1><p>代跑 · 蜡烛 · 代币 · 每日任务</p></div>';
echo '<div class="container"><div class="card">';
if ($msg) echo '<p class="' . (str_starts_with($msg, '提交成功') ? 'ok' : 'err') . '">' . $msg . '</p>';
echo '<h2>提交代跑需求</h2>';
echo '<form method="post" class="form-grid">';
echo '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
echo '<label class="full">选择服务类型（可多选）</label>';
echo '<div class="types-picker full">';
$typeGroups = [
    '基础' => ['每日任务'],
    '蜡烛' => ['10蜡烛', '15蜡烛', '20蜡烛'],
    '服务' => ['挂饭', '包季', '包毕业', '献祭', '试炼'],
    '地图' => ['晨岛', '云野', '雨林', '霞谷', '暮土', '禁阁'],
];
foreach ($typeGroups as $grp => $items) {
    echo '<div class="type-group"><span class="type-group-label">' . h($grp) . '</span><div class="type-group-items">';
    foreach ($items as $tt) {
        echo '<label class="type-chip"><input type="checkbox" name="types[]" value="' . h($tt) . '"><span>' . h($tt) . '</span></label>';
    }
    echo '</div></div>';
}
echo '</div>';
echo '<label class="full">需求说明 <textarea name="info" rows="3" required placeholder="例如：每天两个图+每日任务+代币，跑三周（周一到周五）"></textarea></label>';
echo '<label class="full">联系方式（QQ/微信，选填） <input type="text" name="contact"></label>';
echo '<button type="submit" class="btn full">提交订单</button>';
echo '</form></div>';
if ($bosses) echo '<div class="card"><h2>接单老板</h2><ul class="boss-list">' . implode('', array_map(fn($b) => '<li>' . h($b['name']) . (h($b['game_id']) ? ' · ' . h($b['game_id']) : '') . '</li>', $bosses)) . '</ul></div>';
echo '</div></body></html>';