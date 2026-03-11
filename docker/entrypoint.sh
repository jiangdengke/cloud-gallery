#!/bin/sh

# Cloud Gallery 容器入口脚本（业务部署辅助）
# 目标：让 `docker compose up -d` 首次启动即可可用（自动生成 APP_KEY、自动迁移等）

set -e

APP_DIR="/var/www"
APP_KEY_FILE_DEFAULT="${APP_DIR}/storage/app_key"
SQLITE_DB_DEFAULT="${APP_DIR}/storage/database.sqlite"

cd "${APP_DIR}"

# -----------------------------
# 1) 确保 APP_KEY 可用（持久化）
# -----------------------------
# Laravel 需要 APP_KEY 用于加密/签名等能力。
# - 若外部已通过环境变量提供 APP_KEY，则直接使用；
# - 否则从 storage/app_key 读取（持久化在 volume 中）；
# - 再否则生成一个新的 key，并写入 storage/app_key。
if [ -z "${APP_KEY:-}" ]; then
    APP_KEY_FILE="${APP_KEY_FILE:-$APP_KEY_FILE_DEFAULT}"

    if [ -s "${APP_KEY_FILE}" ]; then
        APP_KEY="$(cat "${APP_KEY_FILE}")"
    else
        APP_KEY="$(php -r 'echo \"base64:\".base64_encode(random_bytes(32));')"

        mkdir -p "$(dirname "${APP_KEY_FILE}")"
        tmp_key_file="$(mktemp)"
        printf '%s' "${APP_KEY}" > "${tmp_key_file}"
        mv "${tmp_key_file}" "${APP_KEY_FILE}"

        chmod 600 "${APP_KEY_FILE}" 2>/dev/null || true
        chown www-data:www-data "${APP_KEY_FILE}" 2>/dev/null || true
    fi

    export APP_KEY
fi

# -----------------------------
# 2) SQLite：自动准备数据库文件
# -----------------------------
# 默认 compose 场景使用 SQLite 更方便（无需额外数据库容器）。
# 若 DB_CONNECTION=sqlite 且未设置 DB_DATABASE，则默认落到 storage/database.sqlite（持久化）。
if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ]; then
    if [ -z "${DB_DATABASE:-}" ]; then
        export DB_DATABASE="${SQLITE_DB_DEFAULT}"
    fi

    if [ ! -f "${DB_DATABASE}" ]; then
        mkdir -p "$(dirname "${DB_DATABASE}")"
        touch "${DB_DATABASE}"
        chmod 664 "${DB_DATABASE}" 2>/dev/null || true
        chown www-data:www-data "${DB_DATABASE}" 2>/dev/null || true
    fi
fi

# -----------------------------
# 3) 自动迁移（默认开启）
# -----------------------------
# 迁移是幂等的：已执行过的迁移不会重复执行。
RUN_MIGRATIONS_NORMALIZED="$(printf '%s' "${RUN_MIGRATIONS:-1}" | tr '[:upper:]' '[:lower:]')"
if [ "${RUN_MIGRATIONS_NORMALIZED}" = "1" ] || [ "${RUN_MIGRATIONS_NORMALIZED}" = "true" ] || [ "${RUN_MIGRATIONS_NORMALIZED}" = "yes" ]; then
    php artisan migrate --force --no-interaction
fi

# 最后执行容器原本的命令（默认 apache2-foreground）
exec "$@"

