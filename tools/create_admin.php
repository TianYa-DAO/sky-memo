<?php
require __DIR__ . '/../config.php';
require __DIR__ . '/../app/db.php';
require __DIR__ . '/../app/auth.php';
if ($argc < 3) {
    fwrite(STDERR, "用法: php tools/create_admin.php <用户名> <密码>\n");
    exit(1);
}
admin_create(get_pdo(), $argv[1], $argv[2]);
echo "管理员 {$argv[1]} 已创建\n";