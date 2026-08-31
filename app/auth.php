<?php
// 管理员认证 + CSRF

function admin_create(PDO $pdo, string $username, string $password): int {
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $st = $pdo->prepare("INSERT INTO users (username, password_hash) VALUES (?,?)");
    $st->execute([$username, $hash]);
    return (int)$pdo->lastInsertId();
}

function admin_login(PDO $pdo, string $username, string $password): ?array {
    $st = $pdo->prepare("SELECT * FROM users WHERE username=?");
    $st->execute([$username]);
    $row = $st->fetch();
    if ($row === false || !password_verify($password, $row['password_hash'])) return null;
    return $row;
}

function require_login(): void {
    session_start();
    if (empty($_SESSION['admin_id'])) {
        header('Location: index.php?action=login');
        exit;
    }
}

// CSRF
function csrf_token(): string {
    session_start();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function csrf_check(): void {
    session_start();
    if (empty($_POST['csrf']) || $_POST['csrf'] !== ($_SESSION['csrf'] ?? '')) {
        http_response_code(403);
        exit('CSRF 校验失败');
    }
}