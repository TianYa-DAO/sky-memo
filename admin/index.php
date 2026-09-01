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
    <link rel="stylesheet" href="/assets/style.css"></head><body>';
    if ($showNav) echo $nav;
}
function page_footer(): void {
    echo '<script src="/assets/app.js"></script></body></html>';
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
    echo '<div class="container"><h2 class="page-title">今日待办</h2>';
    echo '<p class="page-sub">' . date('m月d日 l') . ' — 点亮今天的蜡烛</p>';
    echo '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
    echo '<div class="summary">'
        . '<div class="sum-item"><span class="sum-num">' . $sum['fig'] . '</span><span class="sum-label">图</span></div>'
        . '<div class="sum-item"><span class="sum-num">' . $sum['task'] . '</span><span class="sum-label">任务</span></div>'
        . '<div class="sum-item"><span class="sum-num">' . $sum['cur'] . '</span><span class="sum-label">代币</span></div>'
        . '<div class="sum-item"><span class="sum-num">' . count($todos) . '</span><span class="sum-label">在跑单</span></div>'
        . '<div class="sum-item"><span class="sum-num">¥' . number_format(array_reduce($todos, fn($s,$t)=>$s+(float)($t['price']??0), 0), 1) . '</span><span class="sum-label">今日应收</span></div>'
        . '</div>';
    if (!$todos) echo '<div class="card"><p class="empty"><span class="empty-icon">🕯️</span>今天没有待办<br>所有蜡烛都已点亮，好好休息～</p></div>';
    foreach ($todos as $t) {
        $done = $t['done_today'];
        echo '<div class="todo-card' . ($done ? ' done' : '') . '">';
        echo '<div class="candle-wrap">' . candle_svg($done, 44) . '</div>';
        echo '<div class="t-body">';
        echo '<div class="t-head"><span class="t-name">' . h($t['boss_name']) . '</span><span class="t-no">' . h($t['order_no']) . '</span></div>';
        echo '<div class="t-stats">';
        foreach (parse_types($t['types'] ?? []) as $tt) echo '<span class="t-stat">' . h($tt) . '</span>';
        echo '<span class="t-stat">¥' . number_format((float)($t['price'] ?? 0), 1) . '</span>';
        echo '</div>';
        echo '<div class="t-meta">' . weekdays_label($t['weekdays']) . ' · 至 ' . $t['end_date'] . '</div>';
        echo '<label class="toggle"><input type="checkbox" data-oid="' . $t['id'] . '" ' . ($done ? 'checked' : '') . '><span class="check-label">' . ($done ? '已点亮' : '今日完成') . '</span></label>';
        echo '</div></div>';
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
        // 任务类型多选
        $typesRaw = $_POST['types'] ?? [];
        $typesList = is_array($typesRaw) ? $typesRaw : [$typesRaw];
        $typesList = array_values(array_filter(array_map('trim', $typesList)));
        if (empty($typesList)) $typesList = ['每日任务'];
        // 调度模式：repeat_weekly 1=周几 / 0=指定日期
        $repeatWeekly = isset($_POST['repeat_weekly']) && $_POST['repeat_weekly'] === '0' ? 0 : 1;
        $selectedDates = $repeatWeekly === 0 && !empty($_POST['selected_dates'])
            ? array_values(array_filter(array_map('trim', explode(',', $_POST['selected_dates']))))
            : [];
        $period = $_POST['period'] ?? 'weekly';
        // 老板：支持数字id或自由输入昵称
        $bossInput = $_POST['boss_id'] ?? '';
        $bossId = resolve_boss_id($pdo, $bossInput);
        if (($_POST['do'] ?? '') === 'create') {
            order_create($pdo, $bossId, $typesList,
                $period, $wdStr, $_POST['start_date'], $_POST['end_date'],
                (float)$_POST['price'], $_POST['status'] ?? '进行中', $_POST['notes'] ?? '',
                $repeatWeekly, $selectedDates, $_POST['payment_status'] ?? '未付');
        } elseif (($_POST['do'] ?? '') === 'edit' && isset($_POST['id'])) {
            order_update($pdo, (int)$_POST['id'], [
                'boss_id' => $bossId, 'types' => $typesList,
                'period' => $period,
                'weekdays' => $wdStr, 'start_date' => $_POST['start_date'], 'end_date' => $_POST['end_date'],
                'price' => (float)$_POST['price'], 'payment_status' => $_POST['payment_status'] ?? '未付',
                'status' => $_POST['status'] ?? '进行中', 'notes' => $_POST['notes'] ?? '',
                'repeat_weekly' => $repeatWeekly, 'selected_dates' => $selectedDates,
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
    $f = $editing ?? ['id' => 0, 'boss_id' => '', 'types' => [], 'repeat_weekly' => 1, 'selected_dates' => [],
        'daily_figure' => 0, 'daily_task' => 0, 'daily_currency' => 0,
        'weekdays' => '[1,2,3,4,5]', 'start_date' => date('Y-m-d'), 'end_date' => date('Y-m-d', strtotime('+21 day')),
        'price' => 0, 'payment_status' => '未付', 'status' => '进行中', 'notes' => ''];
    $wd = $editing ? (json_decode($editing['weekdays'], true) ?: []) : [1, 2, 3, 4, 5];
    $selTypes = $editing ? parse_types($editing['types'] ?? '') : ['每日任务'];
    $rw = $editing ? (int)($editing['repeat_weekly'] ?? 1) : 1;
    $selDates = $editing ? (json_decode($editing['selected_dates'] ?? '[]', true) ?: []) : [];
    $editBoss = $editing ? boss_get($pdo, (int)$editing['boss_id']) : null;
    $catalog = service_catalog();
    echo '<div class="card"><h3>' . ($editing ? '编辑订单 #' . $editing['id'] : '新建订单') . '</h3>';
    echo '<form method="post" class="form-grid" action="index.php?action=orders" id="order-form">';
    echo '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
    echo '<input type="hidden" name="do" value="' . ($editing ? 'edit' : 'create') . '">';
    if ($editing) echo '<input type="hidden" name="id" value="' . $editing['id'] . '">';
    echo '<input type="hidden" name="price" id="price-hidden" value="' . (float)$f['price'] . '">';
    echo '<input type="hidden" id="catalog-json" value="' . h(service_catalog_json()) . '">';
    // 老板
    echo '<label>老板（可输入新名字）<input type="text" name="boss_id" list="boss-list" value="' . h($editBoss['name'] ?? '') . '" placeholder="选择已有老板或直接输入昵称" required></label>';
    echo '<datalist id="boss-list">';
    foreach ($bosses as $b) echo '<option value="' . h($b['name']) . '">';
    echo '</datalist>';
    // 任务类型多选（按业务分组，显示描述+价格）
    echo '<label class="full">服务类型（可多选）</label>';
    $typeGroups = [
        '基础' => ['每日任务'],
        '蜡烛' => ['10蜡烛', '15蜡烛', '20蜡烛'],
        '服务' => ['挂饭', '包季', '包毕业', '献祭', '试炼'],
        '地图' => ['晨岛', '云野', '雨林', '霞谷', '暮土', '禁阁'],
    ];
    echo '<div class="types-picker full">';
    foreach ($typeGroups as $grp => $items) {
        echo '<div class="type-group"><span class="type-group-label">' . h($grp) . '</span><div class="type-group-items">';
        foreach ($items as $tt) {
            $svc = $catalog[$tt] ?? [];
            $priceHint = isset($svc['daily']) ? $svc['daily'] . 'r/天' : (isset($svc['per_run']) ? $svc['per_run'] . 'r/次' : (isset($svc['per_map']) ? $svc['per_map'] . 'r/图' : ''));
            $checked = in_array($tt, $selTypes, true) ? ' checked' : '';
            echo '<label class="type-chip" title="' . h($svc['desc'] ?? '') . '"><input type="checkbox" name="types[]" value="' . h($tt) . '"' . $checked . '><span>' . h($tt) . '<small class="chip-price">' . h($priceHint) . '</small></span></label>';
        }
        echo '</div></div>';
    }
    echo '</div>';
    // 服务说明（选中类型的描述合集）
    echo '<div class="full svc-desc-box" id="svc-desc-box"></div>';
    // 安排方式（每周固定/指定日期）
    echo '<label class="full">安排方式</label>';
    echo '<div class="full sched-mode">';
    echo '<label class="sched-opt"><input type="radio" name="repeat_weekly" value="1"' . ($rw === 1 ? ' checked' : '') . ' onchange="toggleSched(this.value)"><span>每周固定（选周几）</span></label>';
    echo '<label class="sched-opt"><input type="radio" name="repeat_weekly" value="0"' . ($rw === 0 ? ' checked' : '') . ' onchange="toggleSched(this.value)"><span>指定日期（日历点选）</span></label>';
    echo '</div>';
    // 周几
    echo '<div class="full sched-panel" id="panel-weekdays"' . ($rw === 1 ? '' : ' style="display:none"') . '>';
    echo '<label>每周哪几天</label><div class="wd-picker">';
    $map = [1 => '一', 2 => '二', 3 => '三', 4 => '四', 5 => '五', 6 => '六', 7 => '日'];
    foreach ($map as $k => $v) {
        $chk = in_array($k, $wd, true) ? ' checked' : '';
        echo '<label class="wd-chip"><input type="checkbox" name="weekdays[]" value="' . $k . '"' . $chk . '><span>周' . $v . '</span></label>';
    }
    echo '</div></div>';
    // 日历
    echo '<div class="full sched-panel" id="panel-dates"' . ($rw === 0 ? '' : ' style="display:none"') . '>';
    echo '<label>点击日历选择日期</label>';
    echo '<input type="hidden" name="selected_dates" id="selected-dates" value="' . h(implode(',', $selDates)) . '">';
    echo '<div id="date-grid" class="date-grid"></div>';
    echo '<p class="date-tip" id="date-tip">已选 <b id="date-count">' . count($selDates) . '</b> 天</p>';
    echo '</div>';
    echo '<label>开始日期 <input type="date" name="start_date" id="start-date" value="' . $f['start_date'] . '" required></label>';
    echo '<label>结束日期 <input type="date" name="end_date" id="end-date" value="' . $f['end_date'] . '" required></label>';
    // 价格（手动填写）
    echo '<label class="full">价格（元）</label>';
    echo '<input type="number" step="0.01" min="0" name="price" id="price-input" value="' . (float)$f['price'] . '" placeholder="手动填写价格">';
    echo '<label>付款 <select name="payment_status">';
    foreach (['未付', '定金', '已付'] as $ps) echo '<option' . ($f['payment_status'] === $ps ? ' selected' : '') . '>' . $ps . '</option>';
    echo '</select></label>';
    echo '<label>状态 <select name="status">';
    foreach (['进行中', '待接', '已完成', '已取消'] as $s) echo '<option' . ($f['status'] === $s ? ' selected' : '') . '>' . $s . '</option>';
    echo '</select></label>';
    echo '<label class="full">备注 <textarea name="notes" rows="2" placeholder="如：跑图路线、特殊要求等">' . h($f['notes']) . '</textarea></label>';
    echo '<button type="submit" class="btn full">' . ($editing ? '保存修改' : '创建订单') . '</button></form></div>';

    // 服务说明 JS（自动定价已取消，价格手动填写）
    echo '<script>';
    echo 'var catalog = {}; try { catalog = JSON.parse(document.getElementById("catalog-json").value); } catch (e) { console.error("catalog JSON 解析失败", e); }';
    echo 'function getSelectedTypes(){ return [...document.querySelectorAll("input[name=\\"types[]\\"]:checked")].map(c=>c.value); }';
    echo 'function updateDesc(){';
    echo '  const types=getSelectedTypes(), box=document.getElementById("svc-desc-box");';
    echo '  let html=""; types.forEach(t=>{ const s=catalog[t]; if(s) html+=\'<div class="svc-desc-item"><b>\'+t+\'</b> <span>\'+s.desc+"</span></div>"; });';
    echo '  box.innerHTML=html; box.style.display=html?"block":"none";';
    echo '}';
    echo 'document.querySelectorAll("input[name=\\"types[]\\"]").forEach(c=>c.addEventListener("change",updateDesc));';
    echo 'updateDesc();';
    echo '</script>';

    // 订单列表
    echo '<div class="list">';
    foreach ($orders as $o) {
        $boss = boss_get($pdo, (int)$o['boss_id']);
        echo '<div class="row' . ($o['status'] === '已完成' ? ' done' : '') . '">';
        echo '<div class="r-title">' . h($o['order_no']) . ' · ' . h($boss['name'] ?? '?') . ' · ' . h(types_label($o['types'] ?? ''))
            . '<span class="badge ' . h($o['status']) . '">' . h($o['status']) . '</span>'
            . '<span class="badge pay">¥' . (float)($o['price'] ?? 0) . '</span></div>';
        $sched = (int)($o['repeat_weekly'] ?? 1) === 1
            ? '每周：' . weekdays_label($o['weekdays'])
            : '指定：' . count(json_decode($o['selected_dates'] ?? '[]', true) ?: []) . '天';
        echo '<div class="r-meta">' . $sched . ' · ' . $o['start_date'] . ' ~ ' . $o['end_date'] . ' · ¥' . (float)($o['price'] ?? 0) . '</div>';
        if ($o['notes']) echo '<div class="r-note">' . h($o['notes']) . '</div>';
        echo '<div class="r-actions">';
        echo '<form method="post" class="inline" action="index.php?action=orders"><input type="hidden" name="csrf" value="' . csrf_token() . '"><input type="hidden" name="do" value="status"><input type="hidden" name="id" value="' . $o['id'] . '">';
        echo '<select name="status" onchange="this.form.submit()">';
        foreach (['待接', '进行中', '已完成', '已取消'] as $s) echo '<option' . ($o['status'] === $s ? ' selected' : '') . '>' . $s . '</option>';
        echo '</select><select name="payment_status" onchange="this.form.submit()">';
        foreach (['未付', '定金', '已付'] as $ps) echo '<option' . ($o['payment_status'] === $ps ? ' selected' : '') . '>' . $ps . '</option>';
        echo '</select></form>';
        echo '<a class="btn small" href="index.php?action=orders&edit=' . $o['id'] . '">编辑</a> ';
        echo '<form method="post" class="inline" action="index.php?action=orders" onsubmit="return confirm(\'删除该订单？\')"><input type="hidden" name="csrf" value="' . csrf_token() . '"><input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="' . $o['id'] . '"><button class="btn small danger">删除</button></form>';
        echo '</div></div>';
    }
    echo '</div></div>';

    // 调度切换 + 日历点选
    echo <<<'HTML'
<script>
function toggleSched(mode) {
    document.getElementById('panel-weekdays').style.display = mode === '1' ? '' : 'none';
    document.getElementById('panel-dates').style.display = mode === '0' ? '' : 'none';
    if (mode === '0') buildDateGrid();
}
function parseDates() {
    var v = document.getElementById('selected-dates').value;
    return v ? v.split(',').filter(Boolean) : [];
}
// 本地时区日期格式化：避免 toISOString() 的 UTC 偏移导致(UTC+8)跨月日期差一天、末尾日期选不出去
function fmtLocal(d) {
    var mm = ('0' + (d.getMonth() + 1)).slice(-2);
    var dd = ('0' + d.getDate()).slice(-2);
    return d.getFullYear() + '-' + mm + '-' + dd;
}
// 'YYYY-MM-DD' -> 本地零点 Date（不能用 new Date(s+'T00:00:00') 或 new Date(s)，避免时区/UTC 解析差异）
function parseYmd(s) {
    var p = s.split('-');
    return new Date(+p[0], +p[1] - 1, +p[2]);
}
function buildDateGrid() {
    var grid = document.getElementById('date-grid');
    var start = document.getElementById('start-date').value;
    var end = document.getElementById('end-date').value;
    if (!start || !end) { grid.innerHTML = '<p class="date-tip">请先填开始/结束日期</p>'; return; }
    var selected = parseDates();
    var cur = parseYmd(start);
    var last = parseYmd(end);
    var html = '';
    ['日','一','二','三','四','五','六'].forEach(function (h) { html += '<div class="dg-hd">' + h + '</div>'; });
    var firstDow = cur.getDay();
    for (var i = 0; i < firstDow; i++) html += '<div class="dg-empty"></div>';
    while (cur <= last) {
        var ys = fmtLocal(cur);
        var on = selected.indexOf(ys) >= 0;
        html += '<div class="dg-cell' + (on ? ' on' : '') + '" data-d="' + ys + '">' + cur.getDate() + '</div>';
        cur.setDate(cur.getDate() + 1);
    }
    grid.innerHTML = html;
    grid.querySelectorAll('.dg-cell').forEach(function (el) {
        el.addEventListener('click', function () {
            var d = el.dataset.d;
            var selected = parseDates();
            var idx = selected.indexOf(d);
            if (idx >= 0) { selected.splice(idx, 1); el.classList.remove('on'); }
            else { selected.push(d); el.classList.add('on'); }
            selected.sort();
            document.getElementById('selected-dates').value = selected.join(',');
            document.getElementById('date-count').textContent = selected.length;
        });
    });
}
document.getElementById('start-date').addEventListener('change', buildDateGrid);
document.getElementById('end-date').addEventListener('change', buildDateGrid);
if (document.querySelector('input[name=repeat_weekly]:checked').value === '0') buildDateGrid();
</script>
HTML;
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