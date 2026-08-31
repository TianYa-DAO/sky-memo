<?php
// 老板 CRUD + AES 加密存储
require_once __DIR__ . '/functions.php';

function boss_create(PDO $pdo, string $name, string $gameId, ?string $account, ?string $password, string $notes): int {
    $st = $pdo->prepare("INSERT INTO bosses (name, game_id, account, password, notes) VALUES (?,?,?,?,?)");
    $st->execute([$name, $gameId,
        $account  === null || $account  === '' ? null : enc_secret($account),
        $password === null || $password === '' ? null : enc_secret($password),
        $notes]);
    return (int)$pdo->lastInsertId();
}

function boss_get(PDO $pdo, int $id): ?array {
    if ($id <= 0) return null;
    $st = $pdo->prepare("SELECT * FROM bosses WHERE id=?");
    $st->execute([$id]);
    $row = $st->fetch();
    return $row === false ? null : $row;
}

function boss_list(PDO $pdo): array {
    return $pdo->query("SELECT * FROM bosses ORDER BY name")->fetchAll();
}

function boss_update(PDO $pdo, int $id, string $name, string $gameId, ?string $account, ?string $password, string $notes): bool {
    $st = $pdo->prepare("UPDATE bosses SET name=?, game_id=?, account=?, password=?, notes=? WHERE id=?");
    $st->execute([$name, $gameId,
        $account  === null || $account  === '' ? null : enc_secret($account),
        $password === null || $password === '' ? null : enc_secret($password),
        $notes, $id]);
    return true;
}

function boss_delete(PDO $pdo, int $id): bool {
    $st = $pdo->prepare("DELETE FROM bosses WHERE id=?");
    $st->execute([$id]);
    return $st->rowCount() > 0;
}

// 按昵称精确查找老板娘 id（无则 null）
function boss_find_by_name(PDO $pdo, string $name): ?int {
    $st = $pdo->prepare("SELECT id FROM bosses WHERE name=? LIMIT 1");
    $st->execute([$name]);
    $id = $st->fetchColumn();
    return $id === false ? null : (int)$id;
}

// 解析老板输入：数字->原id；文本->同名存在则关联，否则自动创建（昵称）
function resolve_boss_id(PDO $pdo, $input): int {
    if (is_numeric($input) && (int)$input > 0) return (int)$input;
    $name = trim((string)$input);
    if ($name === '') return 0;
    $exist = boss_find_by_name($pdo, $name);
    if ($exist !== null) return $exist;
    return boss_create($pdo, $name, '', null, null, '');
}