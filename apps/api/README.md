# apps/api

SisterMart 后端 API — **ThinkPHP 8.1** + MySQL。

## 本地启动（默认）

```bash
# 依赖（首次）
composer install

# 复制环境变量
cp .example.env .env

# 确保 MySQL 已创建库/用户（与 .example.env 一致）
# CREATE DATABASE sistermart CHARACTER SET utf8mb4;
# CREATE USER 'sistermart'@'%' IDENTIFIED BY 'sistermart';
# GRANT ALL ON sistermart.* TO 'sistermart'@'%';

php think run -H 0.0.0.0 -p 8000
```

健康检查：

```bash
curl -s http://127.0.0.1:8000/health | jq .
```

期望：`ok: true`，且 `db.ok: true`。

## M1 范围

- 脚手架 + `/health`（含 MySQL 连通探测）
- **不含**业务模块（自 M2 起）
