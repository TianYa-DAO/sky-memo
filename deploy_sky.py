#!/usr/bin/env python3
# 光遇备忘录部署脚本 v3（凭据从环境变量读取，不含明文）
# 用法: python sky-memo/deploy_sky.py
# 环境变量: SKY_HOST / SKY_USER / SKY_PASS
import paramiko, os, sys, secrets, base64

HOST = os.environ.get("SKY_HOST", "")
USER = os.environ.get("SKY_USER", "ubuntu")
PASSWORD = os.environ.get("SKY_PASS", "")
LOCAL = os.path.dirname(os.path.abspath(__file__))
REMOTE = "/var/www/sky-memo"

def main():
    if not HOST or not PASSWORD:
        print("请设置环境变量 SKY_HOST / SKY_PASS 后再运行")
        sys.exit(1)
    print(f"Deploying sky-memo -> {HOST} ...")
    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client.connect(hostname=HOST, username=USER, password=PASSWORD, timeout=20)

    def run(cmd):
        stdin, stdout, stderr = client.exec_command(cmd, timeout=90)
        return stdout.read().decode("utf-8", "replace"), stderr.read().decode("utf-8", "replace")

    run(f"sudo mkdir -p {REMOTE}")
    run(f"sudo chown -R {USER}:{USER} {REMOTE}")

    sftp = client.open_sftp()
    count = 0
    for root, dirs, files in os.walk(LOCAL):
        if ".git" in root or "backup" in root:
            continue
        rel = os.path.relpath(root, LOCAL)
        for f in files:
            if f in ("config.php", "deploy_sky.py"):
                continue
            local_path = os.path.join(root, f)
            remote_path = f"{REMOTE}/{rel}/{f}" if rel != "." else f"{REMOTE}/{f}"
            remote_path = remote_path.replace("\\", "/")
            try:
                sftp.put(local_path, remote_path)
                count += 1
            except Exception as e:
                print("  FAIL:", remote_path, e)
    sftp.close()
    print(f"  uploaded {count} files")

    # config.php：从环境变量读密码（SKY_DB_PASS / SKY_AES_KEY 可选，缺省随机生成）
    db_pass = os.environ.get("SKY_DB_PASS") or secrets.token_urlsafe(24)
    aes_key = os.environ.get("SKY_AES_KEY") or secrets.token_urlsafe(32)[:32]
    cfg = f"""<?php
date_default_timezone_set('Asia/Shanghai');
define('DB_HOST', 'localhost');
define('DB_NAME', 'sky_memo');
define('DB_USER', 'sky_memo');
define('DB_PASS', '{db_pass}');
define('AES_KEY', '{aes_key}');
define('SITE_URL', 'https://sky.tianyaworld.cn');
"""
    with client.open_sftp() as s:
        with s.open(f"{REMOTE}/config.php", "w") as f:
            f.write(cfg)
    run(f"sudo chmod 600 {REMOTE}/config.php")

    sql = f"""
CREATE DATABASE IF NOT EXISTS sky_memo DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'sky_memo'@'localhost' IDENTIFIED BY '{db_pass}';
GRANT ALL PRIVILEGES ON sky_memo.* TO 'sky_memo'@'localhost';
FLUSH PRIVILEGES;
"""
    b64 = base64.b64encode(sql.encode()).decode()
    run(f"echo {b64} | base64 -d | sudo mysql")
    out, err = run(f"sudo mysql < {REMOTE}/sql/schema.sql")
    if err.strip():
        print("schema stderr:", err[:300])
    out, err = run("sudo mysql -N -e \"SHOW TABLES FROM sky_memo\"")
    print("DB tables:", out.strip().replace("\n", ", "))
    print("\nDone. config.php 已生成；如需改 DB 密码请直接编辑服务器上的 /var/www/sky-memo/config.php")
    client.close()

if __name__ == "__main__":
    main()