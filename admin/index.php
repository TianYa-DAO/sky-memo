<?php
// 后台入口：登录 + 今日待办 + 订单管理 + 老板管理 + 日历
require __DIR__ . '/../config.php';
require __DIR__ . '/../app/db.php';
require __DIR__ . '/../app/functions.php';
require __DIR__ . '/../app/auth.php';
require __DIR__ . '/../app/bosses.php';
require __DIR__ . '/../app/orders.php';
require __DIR__ . '/../app/records.php';

ensure_session();
$pdo = get_pdo();
$action = $_GET['action'] ?? 'todos';
$loginError = '';

// 登录
if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $u = admin_login($pdo, $_POST['username'] ?? '', $_POST['password'] ?? '');
    if ($u) { $_SESSION['admin_id'] = (int)$u['id']; header('Location: index.php'); exit; }
    $loginError = '账号或密码错误';
}
$loggedIn = !empty($_SESSION['admin_id']);

// 登出
if ($action === 'logout') { session_destroy(); header('Location: index.php?action=login'); exit; }

// 未登录只允许看登录页
if (!$loggedIn && $action !== 'login') { $action = 'login'; }

/* ---------- 页面布局辅助 ---------- */
function page_header(string $title, bool $showNav = true): void {
    $nav = '';
    if ($showNav) {
        $nav = '<nav class="topnav"><a href="index.php">今日待办</a>
            <a href="index.php?action=orders">订单</a>
            <a href="index.php?action=bosses">老板</a>
            <a href="index.php?action=calendar">日历</a>
            <a href="index.php?action=logout" class="right">退出</a></nav>';
    }
    echo '<!doctype html><html lang="zh-CN"><head><meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>' . h($title) . ' - 光遇备忘录</title>
    <link rel="stylesheet" href="../public/assets/style.css"></head><body>';
    if ($showNav) echo $nav;
}
function page_footer(): void {
    echo '<script src="../public/assets/app.js"></script></body></html>';
}
function weekdays_label(string $json): string {
    $map = ['1' => '周一', '2' => '周二', '3' => '周三', '4' => '周四', '5' => '周五', '6' => '周六', '7' => '周日'];
    $wd = json_decode($json, true) ?: [];
    $labels = array_map(fn($d) => $map[$d] ?? "周$d", $wd);
    return implode('、', $labels);
}

/* ================= 登录页 ================= */
if ($action === 'login' && !$loggedIn): ?>
<?php page_header('登录', false); ?>
<div class="card login-card">
    <h1>光遇备忘录</h1>
    <?php if ($loginError): ?><p class="err"><?= h($loginError) ?></p><?php endif; ?>
    <form method="post" action="index.php?action=login">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <label>用户名 <input type="text" name="username" required autofocus></label>
        <label>密码 <input type="password" name="password" required></label>
        <button type="submit" class="btn">登录</button>
    </form>
</div>
<?php page_footer(); exit; endif;

/* ================= 今日待办 ================= */
if ($action === 'todos') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        mark_order_done($pdo, (int)$_POST['toggle'], date('Y-m-d'), $_POST['checked'] === '1');
        header('Location: index.php');
        exit;
    }
    $date = date('Y-m-d');
    $todos = today_todos($pdo, $date);
    $sum = ['fig' => 0, 'task' => 0, 'cur' => 0];
    foreach ($todos as $t) {
        $sum['fig'] += (int)$t['daily_figure'];
        $sum['task'] += (int)$t['daily_task'];
        $sum['cur'] += (int)$t['daily_currency'];
    }
    page_header('今日待办');
    echo '<div class="container"><h2>今日待办（' . date('m月d日') . '）</h2>';
    echo '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
    echo '<div class="summary">今天共 <b>' . $sum['fig'] . '</b> 图 / <b>' . $sum['task'] . '</b> 任务 / <b>' . $sum['cur'] . '</b> 代币，涉及 <b>' . count($todos) . '</b> 个单</div>';
    if (!$todos) echo '<p class="empty">今天没有待办，好好休息～</p>';
    foreach ($todos as $t) {
        $done = $t['done_today'];
        echo '<div class="todo-card' . ($done ? ' done' : '') . '">';
        echo '<label class="toggle"><input type="checkbox" data-oid="' . $t['id'] . '" ' . ($done ? 'checked' : '') . '> 今日完成</label>';
        echo '<div class="t-name">' . h($t['boss_name']) . ' · ' . h($t['order_no']) . '</div>';
        echo '<div class="t-meta">' . h($t['type']) . '｜图×' . (int)$t['daily_figure'] . ' + 任务×' . (int)$t['daily_task'] . ' + 代币×' . (int)$t['daily_currency']
            . '｜' . weekdays_label($t['weekdays']) . '｜至 ' . $t['end_date'] . '</div>';
        echo '</div>';
    }
    echo '</div>';
    page_footer();
    exit;
}

/* ================= 订单管理 ================= */
if ($action === 'orders') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        // weekdays 是 <select multiple>，PHP 收到数组；转成 CSV 字符串
        $wdRaw = $_POST['weekdays'] ?? '';
        $wdStr = is_array($wdRaw) ? implode(',', array_map('intval', $wdRaw)) : (string)$wdRaw;
        if ($wdStr === '') $wdStr = '1,2,3,4,5'; // 没选周几则默认工作日
        if (($_POST['do'] ?? '') === 'create') {
            order_create($pdo, (int)$_POST['boss_id'], $_POST['type'],
                (int)$_POST['daily_figure'], (int)$_POST['daily_task'], (int)$_POST['daily_currency'],
                $wdStr, $_POST['start_date'], $_POST['end_date'],
                (float)$_POST['price'], $_POST['status'] ?? '进行中', $_POST['notes'] ?? '');
        } elseif (($_POST['do'] ?? '') === 'edit' && isset($_POST['id'])) {
            order_update($pdo, (int)$_POST['id'], [
                'boss_id' => (int)$_POST['boss_id'], 'type' => $_POST['type'],
                'daily_figure' => (int)$_POST['daily_figure'], 'daily_task' => (int)$_POST['daily_task'], 'daily_currency' => (int)$_POST['daily_currency'],
                'weekdays' => $wdStr, 'start_date' => $_POST['start_date'], 'end_date' => $_POST['end_date'],
                'price' => (float)$_POST['price'], 'payment_status' => $_POST['payment_status'] ?? '未付',
                'status' => $_POST['status'] ?? '进行中', 'notes' => $_POST['notes'] ?? '',
            ]);
        } elseif (($_POST['do'] ?? '') === 'status' && isset($_POST['id'])) {
            order_change_status($pdo, (int)$_POST['id'], $_POST['status'], $_POST['payment_status'] ?? null);
        } elseif (($_POST['do'] ?? '') === 'delete' && isset($_POST['id'])) {
            order_delete($pdo, (int)$_POST['id']);
        }
        header('Location: index.php?action=orders');
        exit;
    }
    $bosses = boss_list($pdo);
    $orders = order_list($pdo);
    $editing = null;
    if (isset($_GET['edit'])) $editing = order_get($pdo, (int)$_GET['edit']);
    page_header('订单管理');
    echo '<div class="container"><h2>订单管理</h2>';

    // 新增/编辑表单
    $f = $editing ?? ['id' => 0, 'boss_id' => '', 'type' => '代跑', 'daily_figure' => 0, 'daily_task' => 0, 'daily_currency' => 0,
        'weekdays' => '[1,2,3,4,5]', 'start_date' => date('Y-m-d'), 'end_date' => date('Y-m-d', strtotime('+21 day')),
        'price' => 0, 'payment_status' => '未付', 'status' => '进行中', 'notes' => ''];
    $wd = $editing ? (json_decode($editing['weekdays'], true) ?: []) : [1, 2, 3, 4, 5];
    echo '<div class="card"><h3>' . ($editing ? '编辑订单 #' . $editing['id'] : '新建订单') . '</h3>';
    echo '<form method="post" class="form-grid" action="index.php?action=orders">';
    echo '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
    echo '<input type="hidden" name="do" value="' . ($editing ? 'edit' : 'create') . '">';
    if ($editing) echo '<input type="hidden" name="id" value="' . $editing['id'] . '">';
    echo '<label>老板 <select name="boss_id">';
    foreach ($bosses as $b) echo '<option value="' . $b['id'] . '"' . ((int)$f['boss_id'] === (int)$b['id'] ? ' selected' : '') . '>' . h($b['name']) . '</option>';
    echo '</select></label>';
    echo '<label>类型 <select name="type"><option' . ($f['type'] === '代跑' ? ' selected' : '') . '>代跑</option>'
        . '<option' . ($f['type'] === '蜡烛' ? ' selected' : '') . '>蜡烛</option>'
        . '<option' . ($f['type'] === '代币' ? ' selected' : '') . '>代币</option>'
        . '<option' . ($f['type'] === '包任务' ? ' selected' : '') . '>包任务</option></select></label>';
    echo '<label>每日图数 <input type="number" name="daily_figure" value="' . (int)$f['daily_figure'] . '" min="0"></label>';
    echo '<label>每日任务数 <input type="number" name="daily_task" value="' . (int)$f['daily_task'] . '" min="0"></label>';
    echo '<label>每日代币数 <input type="number" name="daily_currency" value="' . (int)$f['daily_currency'] . '" min="0"></label>';
    echo '<label>每周哪几天 <select name="weekdays[]" multiple size="5">';
    $map = [1 => '周一', 2 => '周二', 3 => '周三', 4 => '周四', 5 => '周五', 6 => '周六', 7 => '周日'];
    foreach ($map as $k => $v) echo '<option value="' . $k . '"' . (in_array($k, $wd) ? ' selected' : '') . '>' . $v . '</option>';
    echo '</select><small>按住 Ctrl 可多选</small></label>';
    echo '<label>开始日期 <input type="date" name="start_date" value="' . $f['start_date'] . '" required></label>';
    echo '<label>结束日期 <input type="date" name="end_date" value="' . $f['end_date'] . '" required></label>';
    echo '<label>价格 <input type="number" step="0.01" name="price" value="' . $f['price'] . '"></label>';
    echo '<label>付款 <select name="payment_status"><option' . ($f['payment_status'] === '未付' ? ' selected' : '') . '>未付</option><option' . ($f['payment_status'] === '已付' ? ' selected' : '') . '>已付</option></select></label>';
    echo '<label>状态 <select name="status">';
    foreach (['待接', '进行中', '已完成', '已取消'] as $s) echo '<option' . ($f['status'] === $s ? ' selected' : '') . '>' . $s . '</option>';
    echo '</select></label>';
    echo '<label class="full">备注 <textarea name="notes" rows="2">' . h($f['notes']) . '</textarea></label>';
    echo '<button type="submit" class="btn full">' . ($editing ? '保存修改' : '创建订单') . '</button></form></div>';

    // 订单列表
    echo '<div class="list">';
    foreach ($orders as $o) {
        $boss = boss_get($pdo, (int)$o['boss_id']);
        echo '<div class="row' . ($o['status'] === '已完成' ? ' done' : '') . '">';
        echo '<div class="r-title">' . h($o['order_no']) . ' · ' . h($boss['name'] ?? '?') . ' · ' . h($o['type'])
            . '<span class="badge ' . h($o['status']) . '">' . h($o['status']) . '</span>'
            . '<span class="badge pay">' . h($o['payment_status']) . '</span></div>';
        echo '<div class="r-meta">每日：图×' . (int)$o['daily_figure'] . ' + 任务×' . (int)$o['daily_task'] . ' + 代币×' . (int)$o['daily_currency']
            . '｜' . weekdays_label($o['weekdays']) . '｜' . $o['start_date'] . ' ~ ' . $o['end_date'] . '｜¥' . $o['price'] . '</div>';
        if ($o['notes']) echo '<div class="r-note">' . h($o['notes']) . '</div>';
        echo '<div class="r-actions">';
        echo '<form method="post" class="inline" action="index.php?action=orders"><input type="hidden" name="csrf" value="' . csrf_token() . '"><input type="hidden" name="do" value="status"><input type="hidden" name="id" value="' . $o['id'] . '">';
        echo '<select name="status" onchange="this.form.submit()">';
        foreach (['待接', '进行中', '已完成', '已取消'] as $s) echo '<option' . ($o['status'] === $s ? ' selected' : '') . '>' . $s . '</option>';
        echo '</select><select name="payment_status" onchange="this.form.submit()">'
            . '<option' . ($o['payment_status'] === '未付' ? ' selected' : '') . '>未付</option><option' . ($o['payment_status'] === '已付' ? ' selected' : '') . '>已付</option></select></form>';
        echo '<a class="btn small" href="index.php?action=orders&edit=' . $o['id'] . '">编辑</a> ';
        echo '<form method="post" class="inline" action="index.php?action=orders" onsubmit="return confirm(\'删除该订单？\')"><input type="hidden" name="csrf" value="' . csrf_token() . '"><input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="' . $o['id'] . '"><button class="btn small danger">删除</button></form>';
        echo '</div></div>';
    }
    echo '</div></div>';
    page_footer();
    exit;
}

/* ================= 老板管理 ================= */
if ($action === 'bosses') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        $do = $_POST['do'] ?? 'create';
        if ($do === 'create') {
            boss_create($pdo, $_POST['name'], $_POST['game_id'], $_POST['account'] ?? null, $_POST['password'] ?? null, $_POST['notes'] ?? '');
        } elseif ($do === 'edit' && isset($_POST['id'])) {
            boss_update($pdo, (int)$_POST['id'], $_POST['name'], $_POST['game_id'], $_POST['account'] ?? null, $_POST['password'] ?? null, $_POST['notes'] ?? '');
        } elseif ($do === 'delete' && isset($_POST['id'])) {
            boss_delete($pdo, (int)$_POST['id']);
        }
        header('Location: index.php?action=bosses');
        exit;
    }
    $bosses = boss_list($pdo);
    $editing = null;
    if (isset($_GET['edit'])) $editing = boss_get($pdo, (int)$_GET['edit']);
    page_header('老板管理');
    echo '<div class="container"><h2>老板管理</h2>';
    echo '<div class="card"><h3>' . ($editing ? '编辑老板' : '新增老板') . '</h3>';
    echo '<form method="post" class="form-grid" action="index.php?action=bosses">';
    echo '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
    echo '<input type="hidden" name="do" value="' . ($editing ? 'edit' : 'create') . '">';
    if ($editing) echo '<input type="hidden" name="id" value="' . $editing['id'] . '">';
    echo '<label>称呼 <input type="text" name="name" value="' . h($editing['name'] ?? '') . '" required></label>';
    echo '<label>游戏ID <input type="text" name="game_id" value="' . h($editing['game_id'] ?? '') . '"></label>';
    echo '<label>账号 <input type="text" name="account" value="' . ($editing ? h(dec_secret($editing['account'])) : '') . '" autocomplete="off"></label>';
    echo '<label>密码 <input type="text" name="password" value="' . ($editing ? h(dec_secret($editing['password'])) : '') . '" autocomplete="off"></label>';
    echo '<label class="full">备注 <textarea name="notes" rows="2">' . h($editing['notes'] ?? '') . '</textarea></label>';
    echo '<button type="submit" class="btn full">保存</button></form></div>';
    echo '<div class="list">';
    foreach ($bosses as $b) {
        echo '<div class="row"><div class="r-title">' . h($b['name']) . ' <span class="badge">' . h($b['game_id']) . '</span></div>';
        echo '<div class="r-meta">账号已加密存储（' . (empty($b['account']) ? '未填' : '已存') . '）</div>';
        echo '<div class="r-actions"><a class="btn small" href="index.php?action=bosses&edit=' . $b['id'] . '">编辑</a> ';
        echo '<form method="post" class="inline" action="index.php?action=bosses" onsubmit="return confirm(\'删除该老板？\')"><input type="hidden" name="csrf" value="' . csrf_token() . '"><input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="' . $b['id'] . '"><button class="btn small danger">删除</button></form></div></div>';
    }
    echo '</div></div>';
    page_footer();
    exit;
}

/* ================= 日历 ================= */
if ($action === 'calendar') {
    $ym = preg_match('/^\d{4}-\d{2}$/', $_GET['ym'] ?? '') ? $_GET['ym'] : date('Y-m');
    $start = $ym . '-01';
    $end = date('Y-m-t', strtotime($start));
    $days = month_calendar($pdo, $ym);
    // 3天内结束的进行中订单（交单提醒）
    $expiring = [];
    foreach (order_list($pdo, '进行中') as $o) {
        $d = strtotime($o['end_date']);
        $diff = (int)(($d - strtotime(date('Y-m-d'))) / 86400);
        if ($o['end_date'] >= date('Y-m-d') && $diff <= 3) $expiring[] = $o;
    }
    page_header('日历');
    echo '<div class="container"><h2>日历</h2>';
    echo '<div class="cal-nav"><a href="index.php?action=calendar&ym=' . date('Y-m', strtotime($start . ' -1 month')) . '">‹ 上月</a> '
        . '<span>' . $ym . '</span> '
        . '<a href="index.php?action=calendar&ym=' . date('Y-m', strtotime($start . ' +1 month')) . '">下月 ›</a></div>';
    if ($expiring) {
        echo '<div class="alert">⚠️ 即将交单：';
        foreach ($expiring as $o) { $boss = boss_get($pdo, (int)$o['boss_id']); echo h($boss['name'] ?? '?') . '(' . $o['end_date'] . ') '; }
        echo '</div>';
    }
    echo '<div class="cal">';
    foreach (['一', '二', '三', '四', '五', '六', '日'] as $h) echo '<div class="cal-hd">' . $h . '</div>';
    $firstDow = weekday_of($start); // 1=周一
    for ($i = 1; $i < $firstDow; $i++) echo '<div class="cal-cell empty"></div>';
    $d = $start;
    while ($d <= $end) {
        $info = $days[$d] ?? [];
        $isToday = $d === date('Y-m-d');
        $hasActive = (int)($info['active_orders'] ?? 0) > 0;
        echo '<div class="cal-cell' . ($isToday ? ' today' : '') . ($hasActive ? ' active' : '') . '">';
        echo '<div class="cal-date">' . (int)substr($d, 8, 2) . '</div>';
        echo '<div class="cal-info">图' . (int)$info['figure_count'] . ' 任' . (int)$info['task_count'] . ' 币' . (int)$info['currency_count'] . '</div>';
        if ((int)$info['active_orders'] > 0) echo '<div class="cal-badge">' . (int)$info['active_orders'] . '单</div>';
        echo '</div>';
        $d = date('Y-m-d', strtotime($d . ' +1 day'));
    }
    echo '</div></div>';
    page_footer();
    exit;
}

header('Location: index.php');
exit;