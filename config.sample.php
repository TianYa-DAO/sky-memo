<?php
// 复制为 config.php 并填入真实值；config.php 不入 git（chmod 600）
date_default_timezone_set('Asia/Shanghai');
define('DB_HOST', 'localhost');
define('DB_NAME', 'sky_memo');
define('DB_USER', 'sky_memo');
define('DB_PASS', 'CHANGE_ME');
define('AES_KEY', 'CHANGE_ME_32_BYTES_OF_RANDOM'); // 32 字节密钥
define('SITE_URL', 'https://sky.tianyaworld.cn');